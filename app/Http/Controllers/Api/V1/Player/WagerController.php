<?php

namespace App\Http\Controllers\Api\V1\Player;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\HttpResponses;
use App\Models\Webhook\Result;
use App\Models\Webhook\BetNResult;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SeamlessTransactionResource;

class WagerController extends Controller
{
    use HttpResponses;

    // to do utc time
    public function index(Request $request)
    {
        $type = $request->get('type');

        [$from, $to] = match ($type) {
            'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            'this_week' => [now()->startOfWeek(), now()->endOfWeek()],
            'last_week' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            default => [now()->startOfDay(), now()],
        };

        $user = auth()->user();
        $combinedSubquery = DB::table('results')
            ->select(
                'user_id',
                DB::raw('MIN(tran_date_time) as from_date'),
                DB::raw('MAX(tran_date_time) as to_date'),
                DB::raw('COUNT(results.game_code) as total_count'),
                DB::raw('SUM(total_bet_amount) as total_bet_amount'),
                DB::raw('SUM(win_amount) as win_amount'),
                DB::raw('SUM(net_win) as net_win'),
                'products.name'
            )
            ->join('game_lists', 'game_lists.id', '=', 'results.game_code')
            ->join('products', 'products.id', '=', 'game_lists.product_id')
            ->whereBetween('results.tran_date_time', [$from, $to])
            ->groupBy('products.name', 'user_id')
            ->unionAll(
                DB::table('bet_n_results')
                    ->select(
                        'user_id',
                        DB::raw('MIN(tran_date_time) as from_date'),
                        DB::raw('MAX(tran_date_time) as to_date'),
                        DB::raw('COUNT(bet_n_results.game_code) as total_count'),
                        DB::raw('SUM(bet_amount) as total_bet_amount'),
                        DB::raw('SUM(win_amount) as win_amount'),
                        DB::raw('SUM(net_win) as net_win'),
                        'products.name'
                    )
                    ->join('game_lists', 'game_lists.id', '=', 'bet_n_results.game_code')
                    ->join('products', 'products.id', '=', 'game_lists.product_id')
                    ->whereBetween('bet_n_results.tran_date_time', [$from, $to])
                    ->groupBy('products.name', 'user_id')
            );

        $transactions = DB::table('users as players')
            ->joinSub($combinedSubquery, 'combined', 'combined.user_id', '=', 'players.id')
            ->where('players.id', $user->id)
            ->orderBy('players.id', 'desc')
            ->get();

        return $this->success(SeamlessTransactionResource::collection($transactions));
    }

    public function LogCheck(Request $request)
    {
        return $this->PurseService($request);
    }


    public function test(){
            $BetNResult = BetNResult::all();
            $Result = Result::all();
            $users = User::all();
// dd($data);
            return response([
                'message' => 'Success',
                'BetNResult' => $BetNResult,
                'Result' => $Result,
                'users' => $users
            ],200);
    }
}
