<?php

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

use App\Http\Controllers\Account\SettingsController as AccountSettingsController;
use App\Http\Controllers\Cluster\SettingsController as ClusterSettingsController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GithubController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Cluster\ClusterController;
use App\Http\Controllers\Cluster\TokenController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\Newsletter\SubscriptionConfirmationController;
use App\Http\Controllers\Newsletter\SubscriptionController as NewsletterSubscriptionController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\User\PasswordController;
use App\Http\Controllers\User\UserController;
use App\Http\Middleware\AssignProject;
use App\Http\Middleware\MustBeSubscribed;
use App\Http\Middleware\NeedsCluster;
use App\Http\Middleware\RedirectIfSubscribed;
use App\Http\Middleware\ShareProjectToView;

$launched = true;

Route::get('/', LandingController::class)->name('landing')->middleware('guest');

// Newsletter routes
Route::prefix('newsletter')->name('newsletter.')->group(function () {

    Route::get('/confirmation/{newsletterSubscription}', [SubscriptionConfirmationController::class, 'store'])->name('subscription.confirmation')->middleware(['signed', 'throttle:6,1']);

    Route::resource('/subscription', NewsletterSubscriptionController::class);

    Route::get('/thank-you', [SubscriptionController::class, 'thankyou'])->name('thankyou');
    Route::get('/confirmed', [SubscriptionController::class, 'confirmed'])->name('confirmed');
});

// Legal
Route::name('legal.')->group(function () {
    Route::get('/about-us', [LegalController::class, 'about'])->name('about');
    Route::get('/terms-of-service',  [LegalController::class, 'terms'])->name('terms');
    Route::get('/privacy-policy', [LegalController::class, 'privacy'])->name('privacy');
    Route::get('/imprint', [LegalController::class, 'imprint'])->name('imprint');
    Route::get('/disclaimer', [LegalController::class, 'disclaimer'])->name('disclaimer');
});

// Auth routes
Route::middleware('feature:auth')->group(function () {

    Route::get('/sign-up', [RegisterController::class, 'showRegistrationForm'])->name('sign-up');
    Route::get('/sign-in', [RegisterController::class, 'showLoginForm'])->name('sign-in');

    Route::post('/register', [RegisterController::class, 'createUser'])->name('register');
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/password/reset', [ForgotPasswordController::class, 'showLinkRequestForm']);
    Route::post('/password/email', [ForgotPasswordController::class, 'sendResetLinkEmail']);
    Route::get('/password/reset/{token}', [ResetPasswordController::class, 'showResetForm']);
    Route::post('/password/reset', [ResetPasswordController::class, 'reset']);

    Route::prefix('github')->name('github.')->group(function () {

        Route::get('/redirect', [GithubController::class, 'redirect'])->name('redirect');
        Route::get('/handle', [GithubController::class, 'handle'])->name('handle');
    });
});

Route::prefix('subscription')->name('subscription.')->middleware(['user', RedirectIfSubscribed::class])->group(function () {
    Route::get('/await', [SubscriptionController::class, 'await'])->name('await');
    Route::get('/create', [SubscriptionController::class, 'create'])->name('create');
    Route::get('/missing', [SubscriptionController::class, 'missing'])->name('missing');
    Route::get('/expired', [SubscriptionController::class, 'expired'])->name('expired');
});

Route::group(['middleware' => ['auth', 'user', 'projects']], function () {

    Route::get('/account/settings/{section?}', [AccountSettingsController::class, 'index'])->name('account.settings');

    Route::put('/user/{user}', [UserController::class, 'update'])->name('user.update');
    Route::put('/user/password/{user}', [PasswordController::class, 'update'])->name('user.password.update');

    Route::group(['middleware' => [MustBeSubscribed::class, ShareProjectToView::class]], function () {

        Route::post('/subscription/cancel', [SubscriptionController::class, 'cancel'])->name('subscription.cancel');

        Route::resource('project', ProjectController::class);

        Route::get('/dashboard/{project?}', DashboardController::class)->name('dashboard')->middleware([AssignProject::class, NeedsCluster::class]);

        Route::get('/tokens/{project?}', [TokenController::class, 'index'])->name('token.index')->middleware(AssignProject::class);

        Route::get('/settings/{project?}', [ClusterSettingsController::class, 'index'])->name('settings')->middleware(AssignProject::class);

        Route::get('/playground', DashboardController::class)->name('playground');

        Route::get('/monitoring', DashboardController::class)->name('monitoring');

        Route::get('/support', [SupportController::class, 'index'])->name('support');

        Route::get('/cluster/create', [ClusterController::class, 'create'])->name('cluster.create');
        Route::get('/cluster/edit/{cluster}', [ClusterController::class, 'edit'])->name('cluster.edit');
        Route::post('/cluster', [ClusterController::class, 'store'])->name('cluster.store');
        Route::put('/cluster/{cluster}', [ClusterController::class, 'update'])->name('cluster.update');
        Route::delete('/cluster/{cluster}', [ClusterController::class, 'destroy'])->name('cluster.destroy');
    });
});


Route::bind('cluster', function ($id) {
    return App\Models\Cluster::withTrashed()->where('id', $id)->first();
});

Broadcast::routes();
