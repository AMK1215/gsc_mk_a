<?php

namespace App\Http\Controllers\Api\V1\Game;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LaunchGameController extends Controller
{
    private const WEB_PLAT_FORM = 0;

    private const ENG_LANGUAGE_CODE = 1;

    public function launchGame(Request $request)
    {
        // Log::info('LaunchGameController:launchGame', [
        //     'request' => $request->all(),
        // ]);

        $validatedData = $request->validate([
            'provider_id' => 'required|integer',
            'type_id' => 'required|integer',
            'game_id' => 'required',
        ]);

        $user = Auth::user();
        $operatorCode = Config::get('game.api.operator_code');
        $secretKey = Config::get('game.api.secret_key');
        $apiUrl = Config::get('game.api.url').'/Seamless/LaunchGame';
        $password = Config::get('game.api.password');
        // Generate the signature
        $requestTime = now()->format('YmdHis');
        $signature = md5($operatorCode.$requestTime.'launchgame'.$secretKey);

        // Prepare the payload
        $data = [
            'OperatorCode' => $operatorCode,
            'MemberName' => $user->user_name, // Assume username is the member identifier
            'DisplayName' => $validatedData['displayName'] ?? $user->name,
            'Password' => $password,
            'ProductID' => $validatedData['provider_id'],
            'GameType' => $validatedData['type_id'],
            'GameID' => $validatedData['game_id'],
            'LanguageCode' => self::ENG_LANGUAGE_CODE,
            'Platform' => self::WEB_PLAT_FORM,
            'IPAddress' => $request->ip(),
            'Sign' => $signature,
            'RequestTime' => $requestTime,
        ];
        Log::debug('API Request Details', [
            'url' => $apiUrl,
            'payload' => $data,
            'full_request' => $request->all(),
        ]);
        try {
            // Send the request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($apiUrl, $data);

            if ($response->successful()) {
                return $response->json();
            }

            return response()->json(['error' => 'API request failed', 'details' => $response->body()], $response->status());
        } catch (\Throwable $e) {

            return response()->json(['error' => 'An unexpected error occurred', 'exception' => $e->getMessage()], 500);
        }
    }
}
