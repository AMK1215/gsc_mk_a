<?php

use App\Http\Controllers\Admin\Agent\AgentController;
use App\Http\Controllers\Admin\Agent\AgentReportController;
use App\Http\Controllers\Admin\BankController;
use App\Http\Controllers\Admin\BannerAds\BannerAdsController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BannerTextController;
use App\Http\Controllers\Admin\BonusController;
use App\Http\Controllers\Admin\BonusTypeController;
use App\Http\Controllers\Admin\ContactController;
use App\Http\Controllers\Admin\Deposit\DepositRequestController;
use App\Http\Controllers\Admin\GameListController;
use App\Http\Controllers\Admin\GameTypeProductController;
use App\Http\Controllers\Admin\GetBetDetailController;
use App\Http\Controllers\Admin\Master\MasterController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentTypeController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\Player\PlayerController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\TransferLog\TransferLogController;
use App\Http\Controllers\Admin\WithDraw\WithDrawRequestController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ReportV2Controller;
use App\Http\Controllers\ResultArchiveController;
use App\Models\Admin\Role;
use App\Http\Controllers\Admin\DailySummaryController;

use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => 'admin', 'as' => 'admin.',
    'middleware' => ['auth', 'checkBanned'],
], function () {

    Route::post('balance-up', [HomeController::class, 'balanceUp'])->name('balanceUp');
    Route::get('logs/{id}', [HomeController::class, 'logs'])
        ->name('logs');
    // Permissions
    Route::resource('permissions', PermissionController::class);
    // Roles
    Route::delete('roles/destroy', [RolesController::class, 'massDestroy'])->name('roles.massDestroy');
    Route::resource('roles', RolesController::class);
    // Players
    Route::delete('user/destroy', [PlayerController::class, 'massDestroy'])->name('user.massDestroy');
    Route::resource('banks', BankController::class);
    Route::post('/bank-status', [BankController::class, 'updateStatus'])->name('bank.status');
    Route::resource('paymentType', PaymentTypeController::class);

    Route::put('player/{id}/ban', [PlayerController::class, 'banUser'])->name('player.ban');
    Route::resource('player', PlayerController::class);
    Route::get('player-cash-in/{player}', [PlayerController::class, 'getCashIn'])->name('player.getCashIn');
    Route::post('player-cash-in/{player}', [PlayerController::class, 'makeCashIn'])->name('player.makeCashIn');
    Route::get('player/cash-out/{player}', [PlayerController::class, 'getCashOut'])->name('player.getCashOut');
    Route::post('player/cash-out/update/{player}', [PlayerController::class, 'makeCashOut'])
        ->name('player.makeCashOut');
    Route::get('player-changepassword/{id}', [PlayerController::class, 'getChangePassword'])->name('player.getChangePassword');
    Route::post('player-changepassword/{id}', [PlayerController::class, 'makeChangePassword'])->name('player.makeChangePassword');

    Route::get('profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::post('profile/change-password/{user}', [ProfileController::class, 'updatePassword'])
        ->name('profile.updatePassword');

    // user profile route get method
    Route::put('/change-password', [ProfileController::class, 'newPassword'])->name('changePassword');
    // PhoneAddressChange route with auth id route with put method
    Route::put('/change-phone-address', [ProfileController::class, 'PhoneAddressChange'])->name('changePhoneAddress');
    Route::put('/change-kpay-no', [ProfileController::class, 'KpayNoChange'])->name('changeKpayNo');
    Route::put('/change-join-date', [ProfileController::class, 'JoinDate'])->name('addJoinDate');
    Route::resource('banners', BannerController::class);
    Route::resource('adsbanners', BannerAdsController::class);
    Route::resource('text', BannerTextController::class);
    Route::resource('/promotions', PromotionController::class);
    Route::resource('products', ProductController::class);

    Route::resource('contact', ContactController::class);
    Route::get('bonustype', [BonusTypeController::class, 'get'])->name('bonustype');
    Route::resource('bonus', BonusController::class);
    Route::get('bonusPlayer', [BonusController::class, 'search'])->name('bonus.search');
    Route::get('gametypes', [GameTypeProductController::class, 'index'])->name('gametypes.index');
    Route::get('gametypes/{game_type_id}/product/{product_id}', [GameTypeProductController::class, 'edit'])->name('gametypes.edit');
    Route::post('gametypes/{game_type_id}/product/{product_id}', [GameTypeProductController::class, 'update'])->name('gametypes.update');

    // game list start
    Route::get('all-game-lists', [GameListController::class, 'index'])->name('gameLists.index');
    Route::get('all-game-lists/{id}', [GameListController::class, 'edit'])->name('gameLists.edit');
    Route::post('all-game-lists/{id}', [GameListController::class, 'update'])->name('gameLists.update');

    Route::patch('gameLists/{id}/toggleStatus', [GameListController::class, 'toggleStatus'])->name('gameLists.toggleStatus');

    Route::patch('hotgameLists/{id}/toggleStatus', [GameListController::class, 'HotGameStatus'])->name('HotGame.toggleStatus');

    // game list end

    Route::resource('agent', AgentController::class);
    Route::get('agent-cash-in/{id}', [AgentController::class, 'getCashIn'])->name('agent.getCashIn');
    Route::post('agent-cash-in/{id}', [AgentController::class, 'makeCashIn'])->name('agent.makeCashIn');
    Route::get('agent/cash-out/{id}', [AgentController::class, 'getCashOut'])->name('agent.getCashOut');
    Route::post('agent/cash-out/update/{id}', [AgentController::class, 'makeCashOut'])
        ->name('agent.makeCashOut');
    Route::put('agent/{id}/ban', [AgentController::class, 'banAgent'])->name('agent.ban');
    Route::get('agent-changepassword/{id}', [AgentController::class, 'getChangePassword'])->name('agent.getChangePassword');
    Route::post('agent-changepassword/{id}', [AgentController::class, 'makeChangePassword'])->name('agent.makeChangePassword');

    Route::resource('master', MasterController::class);
    Route::get('master-cash-in/{id}', [MasterController::class, 'getCashIn'])->name('master.getCashIn');
    Route::post('master-cash-in/{id}', [MasterController::class, 'makeCashIn'])->name('master.makeCashIn');
    Route::get('master/cash-out/{id}', [MasterController::class, 'getCashOut'])->name('master.getCashOut');
    Route::post('master/cash-out/update/{id}', [MasterController::class, 'makeCashOut'])
        ->name('master.makeCashOut');
    Route::put('master/{id}/ban', [MasterController::class, 'banMaster'])->name('master.ban');
    Route::get('master-changepassword/{id}', [MasterController::class, 'getChangePassword'])->name('master.getChangePassword');
    Route::post('master-changepassword/{id}', [MasterController::class, 'makeChangePassword'])->name('master.makeChangePassword');

    Route::get('withdraw', [WithDrawRequestController::class, 'index'])->name('agent.withdraw');
    Route::post('withdraw/{withdraw}', [WithDrawRequestController::class, 'statusChangeIndex'])->name('agent.withdrawStatusUpdate');
    Route::post('withdraw/reject/{withdraw}', [WithDrawRequestController::class, 'statusChangeReject'])->name('agent.withdrawStatusreject');

    Route::get('deposit', [DepositRequestController::class, 'index'])->name('agent.deposit');
    Route::get('deposit/{deposit}', [DepositRequestController::class, 'show'])->name('agent.depositShow');
    Route::post('deposit/{deposit}', [DepositRequestController::class, 'statusChangeIndex'])->name('agent.depositStatusUpdate');
    Route::post('deposit/reject/{deposit}', [DepositRequestController::class, 'statusChangeReject'])->name('agent.depositStatusreject');

    Route::get('transer-log', [TransferLogController::class, 'index'])->name('transferLog');
    Route::group(['prefix' => 'report'], function () {
        // Route::get('index', [ReportController::class, 'index'])->name('report.index');
        // Route::get('/detail/{playerId}', [ReportController::class, 'detail'])->name('report.detail');
        Route::get('report', [ReportController::class, 'index'])->name('report.index');
        Route::get('reports/details/{player_id}', [ReportController::class, 'getReportDetails'])->name('reports.details');
        Route::get('reports/player/{player_id}', [ReportController::class, 'getPlayer'])->name('reports.player.index');
    });

    Route::group(['prefix' => 'reportv2'], function () {
        //v2 with backup
        Route::get('v2index', [ReportV2Controller::class, 'index'])->name('reportv2.index');
        Route::get('/detail/{playerId}', [ReportV2Controller::class, 'detail'])->name('reportv2.detail');
    });

    // report all backup
    Route::get('/resultsdata', [ResultArchiveController::class, 'getAllResults'])->name('backup_results.index');
    Route::post('/archive-results', [ResultArchiveController::class, 'archiveResults'])->name('archive.results');

    Route::get('/betNresultsdata', [ResultArchiveController::class, 'getAllBetNResults'])->name('backup_bet_n_results.index');

    Route::post('/archive-betNresults', [ResultArchiveController::class, 'archiveBetNResults'])->name('archive.bet_n_result');

    // get bet deatil
    Route::get('get-bet-detail', [GetBetDetailController::class, 'index'])->name('getBetDetail');
    Route::get('get-bet-detail/{wagerId}', [GetBetDetailController::class, 'getBetDetail'])->name('getBetDetail.show');

    Route::post('/generate-daily-summaries', [DailySummaryController::class, 'generateSummaries'])->name('generate_daily_sammary');

    Route::get('/daily-summaries', [DailySummaryController::class, 'index'])
        ->name('daily_summaries.index');

    Route::get('/seamless-transactions', [DailySummaryController::class, 'SeamlessTransactionIndex'])
        ->name('seamless_transactions.index');
    Route::post('/seamless-transactions/delete', [DailySummaryController::class, 'deleteByDateRange'])
        ->name('seamless_transactions.delete');
});
