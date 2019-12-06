@extends('layouts.homepage')

@section('content')
<div class="h-full mx-auto container">
    <div class="row m-0">
        <div class="col-md-7 col-sm-12">
            <form method="POST" id="register-form" class="mx-auto flex container w-full text-gray-700 h-auto"
                action="{{ route('register') }}">
                @csrf
                <div class="container flex justify-center w-auto block border-gray-200 border rounded bg-white px-4">
                    <div class="row">

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10">
                            <h1 class="pt-5 pb-2 text-xl">Register</h1>
                        </div>
                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 border-t mt-3 pt-2">
                            <span class="text-xs text-gray-500">Basics</span>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="email" class="pb-1 block">Email</label>
                                    <input id="email" type="email"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="email" value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="password" class="pb-1 block">{{ __('Password') }}</label>
                                    <input id="password" type="password"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="password">

                                    @error('password')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">

                                    <label for="password-confirm"
                                        class="pb-1 block">{{ __('Confirm Password') }}</label>
                                    <input id="password-confirm" type="password"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror"
                                        name="password_confirmation" required autocomplete="new-password">
                                    @error('password-confirm')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 border-t mt-6 pt-2">
                            <span class="text-xs text-gray-500">Billing</span>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">
                                    <label for="name" class="pb-1 block">Cardholder name</label>
                                    <input id="card-holder-name" type="text"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"
                                        name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                    <span class="invalid-feedback" role="alert">
                                        <strong>{{ $message }}</strong>
                                    </span>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 pt-3">
                            <div class="box">
                                <div class="mx-auto">
                                    <label for="name" class="pb-1 block">Credit card</label>
                                    <div id="card-element"
                                        class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror">
                                    </div>
                                    <div id="card-errors" role="alert"></div>
                                </div>
                            </div>
                        </div>

                        <input name="method" id="method-field" value="" type="hidden">

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
                            <button id="register-button" data-secret="{{ $intent->client_secret }}"
                                class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide">
                                Register
                            </button>
                        </div>

                        <script src="https://js.stripe.com/v3/" type="application/javascript"></script>

                        <script type="application/javascript">
                            var stripe = Stripe('pk_test_c9qTG6rra0eQdTd6n7Nhcqka00a3YibJYB');

                            var elements = stripe.elements();

                            // Custom styling can be passed to options when creating an Element.
                            // (Note that this demo uses a wider set of styles than the guide below.)
                            var style = {
                                hidePostalCode: true,
                                base: {
                                    backgroundColor: '#edf2f7',
                                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                                    fontSmoothing: 'antialiased',
                                    fontSize: '16px',
                                    '::placeholder': {
                                        color: '#aab7c4'
                                    }
                                },
                                invalid: {
                                    color: '#fa755a',
                                    iconColor: '#fa755a'
                                }
                            };

                            // Create an instance of the card Element.
                            var card = elements.create('card', {
                                style: style
                            });

                            console.log('hoejoerklej');

                            // Add an instance of the card Element into the `card-element` <div>.
                            card.mount('#card-element');

                            const cardHolderName = document.getElementById('card-holder-name');
                            const cardButton = document.getElementById('register-button');
                            const clientSecret = cardButton.dataset.secret;
                            const methodInput = document.getElementById('method-field');
                            const form = document.getElementById("register-form")

cardButton.addEventListener('click', async (e) => {
    e.preventDefault();

    const { setupIntent, error } = await stripe.handleCardSetup(
        clientSecret, card, {

            payment_method_data: {
                billing_details: { name: cardHolderName.value }
            }
        }
    );

    if (error) {
        console.log(error);
    } else {
        methodInput.value = setupIntent.payment_method
        form.submit();
    }
});

                        </script>
                    </div>
            </form>
        </div>
    </div>
    <div class="col-md-5 col-sm-12 first-xs last-md">
        <div class="row m-0">
            @include('auth.register.cards.hobby')
        </div>
    </div>
</div>
@endsection
