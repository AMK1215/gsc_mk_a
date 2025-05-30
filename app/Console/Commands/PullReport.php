<?php

namespace App\Console\Commands;

use App\Models\Admin\GameList;
use App\Models\Report;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PullReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:pull-report';

    protected $operatorCode;

    protected $secretKey;

    protected $apiUrl;

    public const VERSION_KEY = 1;

    public function __construct()
    {
        parent::__construct();
        $this->operatorCode = config('game.api.operator_code');
        $this->secretKey = config('game.api.secret_key');
        $this->apiUrl = config('game.api.url');
    }

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $apiUrl = $this->apiUrl.'/Seamless/PullReport';

        $operatorCode = Config::get('game.api.operator_code');
        $secretKey = Config::get('game.api.secret_key');
        // Generate the signature
        $requestTime = now()->format('YmdHis');
        $signature = md5($operatorCode.$requestTime.'pullreport'.$secretKey);
        // Prepare the payload
        $startDate = now()->subMinutes(2);

        $data = [
            'OperatorCode' => $operatorCode,
            'StartDate' => $startDate->format('Y-m-d H:i'),
            'EndDate' => $startDate->copy()->addMinutes(5)->format('Y-m-d H:i'),
            'Sign' => $signature,
            'RequestTime' => $requestTime,
        ];
        //Log::info($data);
        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($apiUrl, $data);

        if ($response->successful()) {
            $data = $response->json();
            //Log::info($data);
            if ($data['Wagers'] != null) {
                $data = $response['Wagers'];
                //Log::info($response);
                // $user = Auth::user(); // Get the authenticated user
                foreach ($data as $report) {
                    $wagerId = Report::where('wager_id', $report['WagerID'])->first();
                    $user = User::where('user_name', $report['MemberName'])->first();
                    $game_name = GameList::where('code', $report['GameID'])->first();
                    $report_game_name = $game_name->name;
                    if ($wagerId) {
                        $wagerId->update([
                            'member_name' => $report['MemberName'],
                            'wager_id' => $report['WagerID'],
                            'product_code' => $report['ProductID'],
                            'game_type_id' => $report['GameType'],
                            //'game_name' => $report['GameID'],
                            'game_name' => $report_game_name,
                            'game_round_id' => $report['GameRoundID'],
                            'valid_bet_amount' => $report['ValidBetAmount'],
                            'bet_amount' => $report['BetAmount'],
                            'payout_amount' => $report['PayoutAmount'],
                            'commission_amount' => $report['CommissionAmount'],
                            'jack_pot_amount' => $report['JackpotAmount'],
                            'jp_bet' => $report['JPBet'],
                            'status' => $report['Status'],
                            'created_on' => $report['CreatedOn'],
                            'modified_on' => $report['ModifiedOn'],
                            // 'settlement_date' => $report['SettlementDate'],
                            'settlement_date' => $report['SettlementDate'] ?? now(),
                            'agent_id' => $user->agent_id, // Store the agent_id
                            'agent_commission' => 0.00,

                            //'agent_commission' => $grossCommission,
                        ]);
                    } else {
                        Report::create([
                            'member_name' => $report['MemberName'],
                            'wager_id' => $report['WagerID'],
                            'product_code' => $report['ProductID'],
                            'game_type_id' => $report['GameType'],
                            'game_name' => $report['GameID'],
                            'game_round_id' => $report['GameRoundID'],
                            'valid_bet_amount' => $report['ValidBetAmount'],
                            'bet_amount' => $report['BetAmount'],
                            'payout_amount' => $report['PayoutAmount'],
                            'commission_amount' => $report['CommissionAmount'],
                            'jack_pot_amount' => $report['JackpotAmount'],
                            'jp_bet' => $report['JPBet'],
                            'status' => $report['Status'],
                            'created_on' => $report['CreatedOn'],
                            'modified_on' => $report['ModifiedOn'],
                            //'settlement_date' => $report['SettlementDate'],
                            'settlement_date' => $report['SettlementDate'] ?? now(),
                            'agent_id' => $user->agent_id, // Store the agent_id
                            'agent_commission' => 0.00,

                        ]);
                    }
                }
            }
            $this->line('<fg=green>Pull Report success</>');
        } else {
            $this->line('<fg=green>Api Call Error</>');
        }
    }
}
