<?php

namespace App\Http\Controllers\Api\V1\Webhook\Gsc;

use App\Enums\SlotWebhookResponseCode;
use App\Enums\TransactionName;
use App\Http\Controllers\Api\V1\Webhook\Gsc\Traits\UseWebhook;
use App\Http\Controllers\Controller;
use App\Http\Requests\Slot\WebhookRequest;
use App\Models\SeamlessEvent;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Slot\SlotWebhookService;
use App\Services\Slot\SlotWebhookValidator;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class GameResultController extends Controller
{
    use UseWebhook;

    public function gameResult(WebhookRequest $request)
    {
        // Log incoming request
        Log::info('GameResult: Request received', [
            'user_id' => $request->getMember()->id,
            'member_name' => $request->getMember()->user_name,
            'message_id' => $request->getMessageID(),
            'transaction_count' => count($request->getTransactions()),
            'request_data' => $request->all(),
        ]);

        $validator = $request->check();

        if ($validator->fails()) {
            Log::error('GameResult: Validation failed', [
                'validation_errors' => $validator->getResponse(),
                'user_id' => $request->getMember()->id,
                'member_name' => $request->getMember()->user_name,
            ]);

            return $validator->getResponse();
        }

        Log::info('GameResult: Validation passed successfully', [
            'user_id' => $request->getMember()->id,
        ]);

        // Check for duplicate transactions early
        if ($validator->hasDuplicateTransaction()) {
            Log::warning('GameResult: Duplicate transaction detected', [
                'transaction_id' => $request->getTransactions()[0]['TransactionID'] ?? null,
                'user_id' => $request->getMember()->id,
                'member_name' => $request->getMember()->user_name,
            ]);

            return SlotWebhookService::buildResponse(
                SlotWebhookResponseCode::DuplicateTransaction,
                $request->getMember()->balanceFloat,
                $request->getMember()->balanceFloat
            );
        }

        Log::info('GameResult: No duplicate transactions detected');

        $event = $this->createEvent($request);

        Log::info('GameResult: Event created', [
            'event_id' => $event->id,
            'user_id' => $request->getMember()->id,
        ]);

        DB::beginTransaction();
        try {
            $before_balance = $request->getMember()->balanceFloat;

            Log::info('GameResult: Starting database transaction', [
                'before_balance' => $before_balance,
                'event_id' => $event->id,
                'user_id' => $request->getMember()->id,
            ]);

            $seamlessTransactionsData = $this->createWagerTransactions($validator->getRequestTransactions(), $event);

            Log::info('GameResult: Wager transactions created', [
                'seamless_transaction_count' => count($seamlessTransactionsData),
                'event_id' => $event->id,
                'user_id' => $request->getMember()->id,
            ]);

            foreach ($seamlessTransactionsData as $index => $seamless_transaction) {
                Log::info('GameResult: Processing seamless transaction', [
                    'transaction_index' => $index,
                    'seamless_transaction_id' => $seamless_transaction->id,
                    'wager_id' => $seamless_transaction->wager_id,
                    'transaction_amount' => $seamless_transaction->transaction_amount,
                    'rate' => $seamless_transaction->rate,
                ]);

                if ($seamless_transaction->transaction_amount < 0) {
                    $from = $request->getMember();
                    $to = User::adminUser();
                    $transfer_type = 'Player to Admin (Loss)';
                } else {
                    $from = User::adminUser();
                    $to = $request->getMember();
                    $transfer_type = 'Admin to Player (Win)';
                }

                Log::info('GameResult: Transfer direction determined', [
                    'transaction_index' => $index,
                    'transfer_type' => $transfer_type,
                    'from_user' => $from->id,
                    'to_user' => $to->id,
                    'amount' => $seamless_transaction->transaction_amount,
                ]);

                $meta = [
                    'wager_id' => $seamless_transaction->wager_id,
                    'event_id' => $request->getMessageID(),
                    'seamless_transaction_id' => $seamless_transaction->id,
                ];

                Log::info('GameResult: Processing transfer', [
                    'transaction_index' => $index,
                    'from_user' => $from->id,
                    'to_user' => $to->id,
                    'transaction_name' => TransactionName::Payout->value,
                    'amount' => $seamless_transaction->transaction_amount,
                    'rate' => $seamless_transaction->rate,
                    'meta' => $meta,
                ]);

                $this->processTransfer(
                    $from,
                    $to,
                    TransactionName::Payout,
                    $seamless_transaction->transaction_amount,
                    $seamless_transaction->rate,
                    $meta
                );

                Log::info('GameResult: Transfer completed successfully', [
                    'transaction_index' => $index,
                    'wager_id' => $seamless_transaction->wager_id,
                    'transfer_type' => $transfer_type,
                ]);
            }

            $request->getMember()->wallet->refreshBalance();

            $after_balance = $request->getMember()->balanceFloat;

            Log::info('GameResult: Balance refreshed', [
                'before_balance' => $before_balance,
                'after_balance' => $after_balance,
                'balance_change' => $after_balance - $before_balance,
                'user_id' => $request->getMember()->id,
            ]);

            DB::commit();

            Log::info('GameResult: Database transaction committed successfully', [
                'event_id' => $event->id,
                'user_id' => $request->getMember()->id,
            ]);

            Log::info('GameResult: Request completed successfully', [
                'user_id' => $request->getMember()->id,
                'member_name' => $request->getMember()->user_name,
                'before_balance' => $before_balance,
                'after_balance' => $after_balance,
                'total_transactions' => count($seamlessTransactionsData),
                'event_id' => $event->id,
            ]);

            return SlotWebhookService::buildResponse(
                SlotWebhookResponseCode::Success,
                $after_balance,
                $before_balance
            );
        } catch (\Exception $e) {
            Log::error('GameResult: Exception occurred during processing', [
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'user_id' => $request->getMember()->id,
                'member_name' => $request->getMember()->user_name,
                'before_balance' => $before_balance ?? 'unknown',
                'event_id' => $event->id ?? 'unknown',
            ]);

            DB::rollBack();

            Log::info('GameResult: Database rolled back after error', [
                'user_id' => $request->getMember()->id,
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ]);
        }
    }
    // public function gameResult(WebhookRequest $request)
    // {
    //     $validator = $request->check();

    //     if ($validator->fails()) {
    //         Log::info('Validator failed', ['response' => $validator->getResponse()]);

    //         return $validator->getResponse();
    //     }

    //     // Check for duplicate transactions early
    //     if ($validator->hasDuplicateTransaction()) {
    //         Log::info('Duplicate transaction detected in controller', [
    //             'transaction_id' => $request->getTransactions()[0]['TransactionID'] ?? null,
    //         ]);

    //         return SlotWebhookService::buildResponse(
    //             SlotWebhookResponseCode::DuplicateTransaction,
    //             $request->getMember()->balanceFloat,
    //             $request->getMember()->balanceFloat
    //         );
    //     }

    //     $event = $this->createEvent($request);

    //     DB::beginTransaction();
    //     try {
    //         $before_balance = $request->getMember()->balanceFloat;

    //         $seamlessTransactionsData = $this->createWagerTransactions($validator->getRequestTransactions(), $event);

    //         foreach ($seamlessTransactionsData as $seamless_transaction) {
    //             if ($seamless_transaction->transaction_amount < 0) {
    //                 $from = $request->getMember();
    //                 $to = User::adminUser();
    //             } else {
    //                 $from = User::adminUser();
    //                 $to = $request->getMember();
    //             }
    //             $this->processTransfer(
    //                 $from,
    //                 $to,
    //                 TransactionName::Payout,
    //                 $seamless_transaction->transaction_amount,
    //                 $seamless_transaction->rate,
    //                 [
    //                     'wager_id' => $seamless_transaction->wager_id,
    //                     'event_id' => $request->getMessageID(),
    //                     'seamless_transaction_id' => $seamless_transaction->id,
    //                 ]
    //             );
    //         }

    //         $request->getMember()->wallet->refreshBalance();

    //         $after_balance = $request->getMember()->balanceFloat;

    //         DB::commit();

    //         return SlotWebhookService::buildResponse(
    //             SlotWebhookResponseCode::Success,
    //             $after_balance,
    //             $before_balance
    //         );
    //     } catch (\Exception $e) {
    //         DB::rollBack();

    //         return response()->json([
    //             'message' => $e->getMessage(),
    //         ]);
    //     }
    // }
}
