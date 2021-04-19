<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ajax Routes
|--------------------------------------------------------------------------
|
| Here is where you can register Ajax routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "ajax" middleware group. Returning json responses
|
*/

use App\Http\Controllers\Cluster\TokenController;
use App\Http\Controllers\Cluster\ValidationController as ClusterValidationController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\Project\ValidationController as ProjectValidationController;
use App\Http\Controllers\Subscription\SubscriptionController;
use App\Http\Controllers\User\ValidationController as UserValidationController;
use App\Http\Middleware\Redirects\RedirecToSameRouteWithProject;
use App\Http\Middleware\Redirects\RedirectToClusterCreateIfHasntCluster;
use Tightenco\Ziggy\Ziggy as Routes;

Route::get('/routes', fn () => response()->json(new Routes()));

Route::get('/notification', [NotificationController::class, 'index'])->name('notification.index');
Route::post('/notification', [NotificationController::class, 'store'])->name('notification.store');
Route::get('/notification/{notification}', [NotificationController::class, 'show'])->name('notification.get');
Route::put('/notification/{notification}', [NotificationController::class, 'update'])->name('notification.update');

Route::get('/cluster/validate/name/{name}', [ClusterValidationController::class, 'name'])->name('cluster.validate.name');

Route::get('/user/validate/email/{email}', [UserValidationController::class, 'email'])->name('user.validate.email');

Route::post('/project/validate/provider', [ProjectValidationController::class, 'provider'])->name('project.validate.provider');

Route::put('/tokens/{project}/regenerate/{clusterToken}', [TokenController::class, 'regenerate'])->name('token.regenerate');

Route::put('/tokens/{project}/toogle/{clusterToken}', [TokenController::class, 'toogle'])->name('token.toogle');

Route::get('/subscription/check', [SubscriptionController::class, 'check'])->name('subscription.check');

Route::get('/dashboard/data/{project?}', [DashboardController::class, 'data'])->name('dashboard.data')->middleware([RedirecToSameRouteWithProject::class, RedirectToClusterCreateIfHasntCluster::class]);
