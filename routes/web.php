<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use App\Http\Controllers\Analysis\MappingController;
use App\Http\Controllers\Analysis\ScoreController;
use App\Http\Controllers\Analysis\StemmingController;
use App\Http\Controllers\Analysis\StopwordController as StopwordsController;
use App\Http\Controllers\Analysis\SynonymController as SynonymsController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GithubController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Cluster\AllowedIpsController;
use App\Http\Controllers\Cluster\BasicAuthController;
use App\Http\Controllers\Cluster\ClusterController;
use App\Http\Controllers\Cluster\TokenController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Indexing\IndexingController;
use App\Http\Controllers\Indexing\PingController;
use App\Http\Controllers\Indexing\PlanController;
use App\Http\Controllers\Indexing\TriggerController;
use App\Http\Controllers\Landing\LandingController;
use App\Http\Controllers\Legal\LegalController;
use App\Http\Controllers\Newsletter\SubscriptionConfirmationController;
use App\Http\Controllers\Newsletter\SubscriptionController as NewsletterSubscriptionController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Playground\PlaygroundController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\SettingsController as ProjectSettingsController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\User\PasswordController;
use App\Http\Controllers\User\SettingsController as AccountSettingsController;
use App\Http\Controllers\User\UserController;
use App\Http\Middleware\Redirects\RedirecToSameRouteWithProject as RedirectToSameRouteWithProject;
use App\Http\Middleware\Redirects\RedirectToClusterCreateIfHasntCluster;
use App\Http\Middleware\Redirects\RedirectToDashboardIfSubscribed;
use App\Http\Middleware\Redirects\RedirectToRenewSubscriptionIfNotSubscribed;
use App\Http\Middleware\Shares\ShareSelectedProjectToView;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;

Route::get('/', LandingController::class)->name('landing')->middleware('guest');

Route::get('/foo', function () {
    ray('bar');
})->name('foo');

// Newsletter routes
Route::prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/confirmation/{newsletterSubscription}', [SubscriptionConfirmationController::class, 'store'])->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::post('newsletter/subscription/create', [NewsletterSubscriptionController::class, 'store'])->name('subscription.store');

    Route::get('/thank-you', [NewsletterSubscriptionController::class, 'thankyou'])->name('thankyou');
    Route::get('/confirmed', [NewsletterSubscriptionController::class, 'confirmed'])->name('confirmed');
});

// Legal
Route::name('legal.')->group(function () {
    Route::get('/about-us', [LegalController::class, 'about'])->name('about');
    Route::get('/terms-of-service', [LegalController::class, 'terms'])->name('terms');
    Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/imprint', [LegalController::class, 'imprint'])->name('imprint');
    Route::get('/disclaimer', [LegalController::class, 'disclaimer'])->name('disclaimer');
});

// Auth routes
Route::middleware('feature:auth')->group(function () {

    Route::get('/sign-up', [RegisterController::class, 'showRegistrationForm'])->name('sign-up');
    Route::get('/sign-in', [LoginController::class, 'showLoginForm'])->name('sign-in');

    Route::post('/register', [RegisterController::class, 'createUser'])->name('register');
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

    Route::prefix('github')->name('github.')->group(function () {

        Route::get('/redirect', [GithubController::class, 'redirect'])->name('redirect');
        Route::get('/handle', [GithubController::class, 'handle'])->name('handle');
    });
});

Route::prefix('subscription')->name('subscription.')->middleware([
    'auth',
    'user',
    RedirectToDashboardIfSubscribed::class
])->group(function () {
    Route::get('/await', [SubscriptionController::class, 'await'])->name('await');
    Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
    Route::get('/missing', [SubscriptionController::class, 'missing'])->name('missing');
    Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');
});

Route::group(['middleware' => ['auth', 'user', 'projects']], function () {

    //Settings
    Route::get('/account/settings/{section?}', [AccountSettingsController::class, 'index'])->name('account.settings')->middleware(ShareSelectedProjectToView::class);
    Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
    Route::put('/user/password/{user}', [PasswordController::class, 'update'])->name('user.password.update');
    Route::delete('/user/{user}', [UserController::class, 'destroy'])->name('user.destroy');

    Route::get('/notification', [NotificationController::class, 'list'])->name('notification.list');

    Route::group(['middleware' => [
        RedirectToRenewSubscriptionIfNotSubscribed::class,
        ShareSelectedProjectToView::class
    ]], function () {

        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

        Route::get('/dashboard/{project?}', [DashboardController::class, 'show'])->name('dashboard')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);

        Route::get('/tokens/{project?}', [TokenController::class, 'index'])->name('token.index')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);

        Route::get('/project/settings/{project?}', [ProjectSettingsController::class, 'index'])->name('settings')->middleware(RedirectToSameRouteWithProject::class);
        Route::put('/project/{project?}', [ProjectController::class, 'update'])->name('project.update');
        Route::post('/project', [ProjectController::class, 'store'])->name('project.store');
        Route::get('/project', [ProjectController::class, 'create'])->name('project.create');

        Route::get('/cluster/create', [ClusterController::class, 'create'])->name('cluster.create')->middleware([]);
        Route::get('/cluster/edit/{cluster}', [ClusterController::class, 'edit'])->name('cluster.edit');
        Route::post('/cluster', [ClusterController::class, 'store'])->name('cluster.store');
        Route::put('/cluster/{cluster}', [ClusterController::class, 'update'])->name('cluster.update');
        Route::delete('/cluster/{cluster}', [ClusterController::class, 'destroy'])->name('cluster.destroy');
        Route::post('/cluster/{cluster}/allowed-ips', [AllowedIpsController::class, 'store'])->name('cluster.allowed-ips.store');
        Route::put('/cluster/{cluster}/allowed-ips/{address}', [AllowedIpsController::class, 'update'])->name('cluster.allowed-ips.update');
        Route::delete('/cluster/{cluster}/allowed-ips/{address}', [AllowedIpsController::class, 'destroy'])->name('cluster.allowed-ips.destroy');

        Route::put('/cluster/basic-auth/{cluster}', [BasicAuthController::class, 'update'])->name('cluster.basic-auth.update');

        Route::get('/indexing/{project?}', IndexingController::class)->name('indexing.indexing')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
        Route::post('/indexing/plan', [PlanController::class, 'store'])->name('indexing.plan.store');
        Route::put('/indexing/plan/{plan}', [PlanController::class, 'update'])->name('indexing.plan.update');
        Route::delete('/indexing/plan/{plan}', [PlanController::class, 'destroy'])->name('indexing.plan.destroy');

        Route::post('/indexing/plan/trigger/{plan}', TriggerController::class)->name('indexing.plan.trigger');
        Route::patch('/indexing/plan/deactivate/{plan}', [PlanController::class, 'deactivate'])->name('indexing.plan.deactivate');
        Route::patch('/indexing/plan/activate/{plan}', [PlanController::class, 'activate'])->name('indexing.plan.activate');

        Route::get('/playground/{project?}', PlaygroundController::class)->name('playground.playground')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);

        Route::get('/analytics/{project?}', AnalyticsController::class)->name('analytics.analytics')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);

        Route::get('/analysis/mapping/{project?}/', MappingController::class)->name('analysis.mapping')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
        Route::get('/analysis/stemming/{project?}/', StemmingController::class)->name('analysis.stemming')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
        Route::get('/analysis/synonyms/{project?}/', SynonymsController::class)->name('analysis.synonyms')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
        Route::get('/analysis/stopwords/{project?}/', StopwordsController::class)->name('analysis.stopwords')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
        Route::get('/analysis/scores/{project?}/', ScoreController::class)->name('analysis.scores')->middleware([RedirectToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
    });
});

Route::get('/indexing/plan/ping/{plan}', PingController::class)->name('indexing.plan.ping')->middleware([
    'throttle:6,1',
    ValidateSignature::class,
]);

Broadcast::routes();
