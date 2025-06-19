<?php

namespace App\Http\Controllers\Api\V1\Webhook\Gsc;

use App\Enums\SlotWebhookResponseCode;
use App\Enums\TransactionName;
use App\Http\Controllers\Api\V1\Webhook\Gsc\Traits\OptimizedBettingProcess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Slot\SlotWebhookRequest;
use App\Models\Admin\GameType;
use App\Models\Admin\GameTypeProduct;
use App\Models\Admin\Product;
use App\Models\User;
use App\Services\Slot\SlotWebhookService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class PlaceBetController extends Controller
{
    use OptimizedBettingProcess;

    public function placeBet(SlotWebhookRequest $request)
    {
        // Log incoming request
        Log::info('PlaceBet: Request received', [
            'user_id' => $request->getMember()->id,
            'member_name' => $request->getMember()->user_name,
            'message_id' => $request->getMessageID(),
            'transaction_count' => count($request->getTransactions()),
            'request_data' => $request->all(),
        ]);

        $userId = $request->getMember()->id;
        $transactions = $request->getTransactions();

        // Extract wager_ids from transactions
        $wagerIds = array_map(function ($transaction) {
            return $transaction['WagerID'] ?? null;
        }, $transactions);
        $wagerIds = array_filter($wagerIds);

        Log::info('PlaceBet: Extracted wager IDs', [
            'wager_ids' => $wagerIds,
            'total_wagers' => count($wagerIds),
        ]);

        if (empty($wagerIds)) {
            Log::warning('PlaceBet: No wager IDs found in request', [
                'transactions' => $transactions,
            ]);
            return response()->json([
                'message' => 'WagerID is required for all transactions.',
            ], 400);
        }

        // Acquire Redis locks for user and wager_ids
        $attempts = 0;
        $maxAttempts = 3;
        $lockUser = false;
        $lockWagers = [];

        Log::info('PlaceBet: Attempting to acquire user lock', [
            'user_id' => $userId,
            'max_attempts' => $maxAttempts,
        ]);

        while ($attempts < $maxAttempts && ! $lockUser) {
            $lockUser = Redis::set("wallet:lock:$userId", true, 'EX', 15, 'NX');
            $attempts++;

            if (! $lockUser) {
                Log::warning('PlaceBet: User lock acquisition failed, retrying', [
                    'user_id' => $userId,
                    'attempt' => $attempts,
                ]);
                sleep(1);
            }
        }

        if (! $lockUser) {
            Log::error('PlaceBet: Failed to acquire user lock after all attempts', [
                'user_id' => $userId,
                'attempts' => $attempts,
            ]);
            return response()->json([
                'message' => 'Another transaction is currently processing for this user. Please try again later.',
                'userId' => $userId,
            ], 409);
        }

        Log::info('PlaceBet: User lock acquired successfully', [
            'user_id' => $userId,
            'attempts' => $attempts,
        ]);

        // Acquire locks for all wager_ids
        foreach ($wagerIds as $wagerId) {
            $attempts = 0;
            $lockWager = false;
            
            Log::info('PlaceBet: Attempting to acquire wager lock', [
                'wager_id' => $wagerId,
                'max_attempts' => $maxAttempts,
            ]);
            
            while ($attempts < $maxAttempts && ! $lockWager) {
                $lockWager = Redis::set("wager:lock:$wagerId", true, 'EX', 15, 'NX');
                $attempts++;
                if (! $lockWager) {
                    Log::warning('PlaceBet: Wager lock acquisition failed, retrying', [
                        'wager_id' => $wagerId,
                        'attempt' => $attempts,
                    ]);
                    sleep(1);
                }
            }
            if (! $lockWager) {
                Log::error('PlaceBet: Failed to acquire wager lock, releasing all locks', [
                    'wager_id' => $wagerId,
                    'attempts' => $attempts,
                    'locked_wagers' => $lockWagers,
                ]);
                // Release all locks and fail
                Redis::del("wallet:lock:$userId");
                foreach ($lockWagers as $lockedWagerId) {
                    Redis::del("wager:lock:$lockedWagerId");
                }

                return response()->json([
                    'message' => "Another transaction is currently processing for wager_id $wagerId. Please try again later.",
                    'wager_id' => $wagerId,
                ], 409);
            }
            $lockWagers[] = $wagerId;
            Log::info('PlaceBet: Wager lock acquired successfully', [
                'wager_id' => $wagerId,
                'attempts' => $attempts,
            ]);
        }

        Log::info('PlaceBet: All locks acquired successfully', [
            'user_id' => $userId,
            'locked_wagers' => $lockWagers,
        ]);

        $validator = $request->check();

        if ($validator->fails()) {
            Log::error('PlaceBet: Validation failed', [
                'validation_errors' => $validator->getResponse(),
                'user_id' => $userId,
            ]);
            Redis::del("wallet:lock:$userId");
            foreach ($lockWagers as $wagerId) {
                Redis::del("wager:lock:$wagerId");
            }

            return $validator->getResponse();
        }

        Log::info('PlaceBet: Validation passed successfully');

        $transactions = $validator->getRequestTransactions();

        if (! is_array($transactions) || empty($transactions)) {
            Log::error('PlaceBet: Invalid transaction data format', [
                'transactions' => $transactions,
                'user_id' => $userId,
            ]);
            Redis::del("wallet:lock:$userId");
            foreach ($lockWagers as $wagerId) {
                Redis::del("wager:lock:$wagerId");
            }

            return response()->json([
                'message' => 'Invalid transaction data format.',
                'details' => $transactions,
            ], 400);
        }

        Log::info('PlaceBet: Transaction data validated', [
            'transaction_count' => count($transactions),
            'transactions' => $transactions,
        ]);

        $before_balance = $request->getMember()->balanceFloat;
        $event = $this->createEvent($request);

        Log::info('PlaceBet: Starting database transaction', [
            'before_balance' => $before_balance,
            'event_id' => $event->id,
            'user_id' => $userId,
        ]);

        DB::beginTransaction();
        try {
            Log::info('PlaceBet: Inserting bets', [
                'transaction_count' => count($transactions),
                'event_id' => $event->id,
            ]);

            $message = $this->insertBets($transactions, $event);

            Log::info('PlaceBet: Bets inserted successfully', [
                'message' => $message,
                'event_id' => $event->id,
            ]);

            foreach ($transactions as $index => $transaction) {
                Log::info('PlaceBet: Processing transaction', [
                    'transaction_index' => $index,
                    'wager_id' => $transaction->WagerID,
                    'transaction_id' => $transaction->TransactionID,
                    'amount' => $transaction->TransactionAmount,
                    'game_type' => $transaction->GameType,
                    'product_id' => $transaction->ProductID,
                ]);

                $fromUser = $request->getMember();
                $toUser = User::adminUser();

                $game_type = GameType::where('code', $transaction->GameType)->first();
                $product = Product::where('code', $transaction->ProductID)->first();
                $game_type_product = GameTypeProduct::where('game_type_id', $game_type->id)
                    ->where('product_id', $product->id)
                    ->first();
                $rate = $game_type_product->rate;

                Log::info('PlaceBet: Game configuration retrieved', [
                    'game_type_id' => $game_type->id,
                    'product_id' => $product->id,
                    'rate' => $rate,
                    'game_type_product_id' => $game_type_product->id,
                ]);

                $meta = [
                    'wager_id' => $transaction->WagerID,
                    'event_id' => $request->getMessageID(),
                    'transaction_id' => $transaction->TransactionID,
                ];

                Log::info('PlaceBet: Processing transfer', [
                    'from_user' => $fromUser->id,
                    'to_user' => $toUser->id,
                    'amount' => $transaction->TransactionAmount,
                    'rate' => $rate,
                    'meta' => $meta,
                ]);

                $this->processTransfer(
                    $fromUser,
                    $toUser,
                    TransactionName::Stake,
                    $transaction->TransactionAmount,
                    $rate,
                    $meta
                );

                Log::info('PlaceBet: Transfer completed successfully', [
                    'transaction_index' => $index,
                    'wager_id' => $transaction->WagerID,
                ]);
            }

            $request->getMember()->wallet->refreshBalance();
            $after_balance = $request->getMember()->balanceFloat;

            Log::info('PlaceBet: Balance refreshed', [
                'before_balance' => $before_balance,
                'after_balance' => $after_balance,
                'balance_change' => $after_balance - $before_balance,
            ]);

            DB::commit();
            
            Log::info('PlaceBet: Database transaction committed successfully');

            Redis::del("wallet:lock:$userId");
            foreach ($lockWagers as $wagerId) {
                Redis::del("wager:lock:$wagerId");
            }

            Log::info('PlaceBet: All locks released successfully', [
                'user_id' => $userId,
                'released_wagers' => $lockWagers,
            ]);

            Log::info('PlaceBet: Request completed successfully', [
                'user_id' => $userId,
                'before_balance' => $before_balance,
                'after_balance' => $after_balance,
                'total_transactions' => count($transactions),
            ]);

            return SlotWebhookService::buildResponse(
                SlotWebhookResponseCode::Success,
                $after_balance,
                $before_balance
            );
        } catch (\Exception $e) {
            Log::error('PlaceBet: Exception occurred during processing', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $userId,
                'before_balance' => $before_balance,
            ]);

            DB::rollBack();
            Redis::del("wallet:lock:$userId");
            foreach ($lockWagers as $wagerId) {
                Redis::del("wager:lock:$wagerId");
            }

            Log::info('PlaceBet: Database rolled back and locks released after error');

            if (str_contains($e->getMessage(), 'Duplicate transaction detected')) {
                Log::warning('PlaceBet: Duplicate transaction detected, returning duplicate response', [
                    'error_message' => $e->getMessage(),
                    'user_id' => $userId,
                ]);

                return SlotWebhookService::buildResponse(
                    SlotWebhookResponseCode::DuplicateTransaction,
                    $before_balance,
                    $before_balance
                );
            }

            Log::error('PlaceBet: Error during placeBet', ['error' => $e]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    // public function placeBet(SlotWebhookRequest $request)
    // {
    //     $userId = $request->getMember()->id;

    //     // Retry logic for acquiring the Redis lock
    //     $attempts = 0;
    //     $maxAttempts = 3;
    //     $lock = false;

    //     while ($attempts < $maxAttempts && ! $lock) {
    //         $lock = Redis::set("wallet:lock:$userId", true, 'EX', 15, 'NX'); // 15 seconds lock
    //         $attempts++;

    //         if (! $lock) {
    //             sleep(1); // Wait for 1 second before retrying
    //         }
    //     }

    //     if (! $lock) {
    //         return response()->json([
    //             'message' => 'Another transaction is currently processing. Please try again later.',
    //             'userId' => $userId,
    //         ], 409); // 409 Conflict
    //     }

    //     // Validate the structure of the request
    //     $validator = $request->check();

    //     if ($validator->fails()) {
    //         // Release Redis lock and return validation error response
    //         Redis::del("wallet:lock:$userId");

    //         return $validator->getResponse();
    //     }

    //     // Retrieve transactions from the request
    //     $transactions = $validator->getRequestTransactions();

    //     // Check if the transactions are in the expected format
    //     if (! is_array($transactions) || empty($transactions)) {
    //         Redis::del("wallet:lock:$userId");

    //         return response()->json([
    //             'message' => 'Invalid transaction data format.',
    //             'details' => $transactions,  // Provide details about the received data for debugging
    //         ], 400);  // 400 Bad Request
    //     }

    //     $before_balance = $request->getMember()->balanceFloat;
    //     $event = $this->createEvent($request);

    //     DB::beginTransaction();
    //     try {
    //         // Insert bets using chunking for better performance
    //         $message = $this->insertBets($transactions, $event);  // Insert bets in chunks

    //         // Process each transaction by transferring the amount
    //         foreach ($transactions as $transaction) {
    //             $fromUser = $request->getMember();
    //             $toUser = User::adminUser();

    //             // Fetch the rate from GameTypeProduct before calling processTransfer()
    //             $game_type = GameType::where('code', $transaction->GameType)->first();
    //             $product = Product::where('code', $transaction->ProductID)->first();
    //             $game_type_product = GameTypeProduct::where('game_type_id', $game_type->id)
    //                 ->where('product_id', $product->id)
    //                 ->first();
    //             $rate = $game_type_product->rate;

    //             $meta = [
    //                 'wager_id' => $transaction->WagerID,
    //                 'event_id' => $request->getMessageID(),
    //                 'transaction_id' => $transaction->TransactionID,
    //             ];

    //             // Call processTransfer with the correct rate
    //             $this->processTransfer(
    //                 $fromUser,
    //                 $toUser,
    //                 TransactionName::Stake,
    //                 $transaction->TransactionAmount,
    //                 $rate,  // Use the fetched rate
    //                 $meta
    //             );
    //         }

    //         // Refresh balance after transactions
    //         $request->getMember()->wallet->refreshBalance();
    //         $after_balance = $request->getMember()->balanceFloat;

    //         DB::commit();

    //         Redis::del("wallet:lock:$userId");

    //         // Return success response
    //         return SlotWebhookService::buildResponse(
    //             SlotWebhookResponseCode::Success,
    //             $after_balance,
    //             $before_balance
    //         );
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Redis::del("wallet:lock:$userId");
    //         Log::error('Error during placeBet', ['error' => $e]);

    //         return response()->json([
    //             'message' => $e->getMessage(),
    //         ], 500);
    //     }
    // }
}
