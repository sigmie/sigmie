<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUser;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Laravel\Paddle\Receipt;

class RegisterController extends Controller
{
    use RegistersUsers;

    protected UserRepository $users;

    public function __construct(UserRepository $userRepository)
    {
        $this->middleware('guest');

        $this->users = $userRepository;
    }

    public function showRegistrationForm(Request $request)
    {
        return Inertia::render('auth/register', [
            'githubUser' => $request->session()->get('githubUser', []),
            'paddleData' => [
                'vendor' => (int) config('services.paddle.vendor_id'),
            ],
        ]);
    }

    public function createUser(StoreUser $request)
    {
        $data = $request->validated();

        $password = (isset($data['password'])) ? Hash::make($data['password']) : null;

        $user = $this->users->create(
            [
                'email' => $data['email'],
                'username' => $data['username'],
                'password' => $password,
                'avatar_url' => '',
                'github' => false
            ]
        );

        event(new Registered($user));

        $paylink = $user->newSubscription('hobby', config('services.paddle.plan_id'))
            ->returnTo(route('await-webhook'))
            ->create();

        return ['paylink' => $paylink];
    }

    public function awaitPaddleWebhook(Request $request)
    {
        $checkoutId = $request->get('checkout');
        $receipt = Receipt::firstWhere('checkout_id', $request->get('checkout'));

        if ($receipt !== null && $receipt->getAttribute('billable')->subscribed('hobby')) {

            $user = $receipt->getAttribute('billable');

            $this->guard()->login($user);

            return redirect()->route('project.create');
        }

        return Inertia::render('auth/register/await-hook', ['checkoutId' => $checkoutId]);
    }

    // /**
    //  * Create a new user instance after a valid registration.
    //  *
    //  * @param  array $data
    //  * @return \App\Models\User
    //  */
    // protected function create(array $data)
    // {
    //     $githubUser = session()->get('githubUser', null);

    //     $email = $data['email'];
    //     $password = (isset($data['password'])) ? Hash::make($data['password']) : '';
    //     $avatar_url = '';

    //     if ($githubUser !== null) {
    //         $avatar_url = $githubUser['avatar_url'];
    //     } else {
    //         $avatar_url = 'https://www.gravatar.com/avatar/';
    //         $avatar_url .= md5(strtolower(trim($email)));
    //     }

    //     return User::create(
    //         [
    //             'email' => $email,
    //             'username' => $data['username'],
    //             'password' => $password,
    //             'avatar_url' => $avatar_url,
    //             'github' => $githubUser !== null
    //         ]
    //     );
    // }
}
