@extends('layouts.homepage')

@section('content')
<div class="h-full mx-auto container w-9/12 sm:w-8/12 xl:w-8/12">
    <div class="row">
        <div class="col-md-7 col-sm-12">
            <form method="POST" id="payment-form" class="mx-auto flex container w-full text-gray-700 h-auto" action="{{ route('register') }}">
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

                                    <label for="email" class="pb-1 block">{{ __('E-Mail Address') }}</label>
                                    <input id="email" type="email" class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required autocomplete="email">

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
                                    <input id="password" type="password" class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror" name="password">

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

                                    <label for="password-confirm" class="pb-1 block">{{ __('Confirm Password') }}</label>
                                    <input id="password-confirm" type="password" class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('password') is-invalid @enderror @error('email') is-invalid @enderror" name="password_confirmation" required autocomplete="new-password">
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
                                    <input id="name" type="text" class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

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
                                    <div id="card-element" class="bg-white focus:outline-none focus:shadow-outline bg-gray-200 rounded py-1 px-4 block w-full appearance-none leading-normal @error('name') is-invalid @enderror"></div>
                                    <div id="card-errors" role="alert"></div>
                                </div>
                            </div>
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

                            // Add an instance of the card Element into the `card-element` <div>.
                            card.mount('#card-element');

                            // Handle real-time validation errors from the card Element.
                            card.addEventListener('change', function(event) {
                                var displayError = document.getElementById('card-errors');
                                if (event.error) {
                                    displayError.textContent = event.error.message;
                                } else {
                                    displayError.textContent = '';
                                }
                            });

                            // Handle form submission.
                            var form = document.getElementById('payment-form');
                            form.addEventListener('submit', function(event) {
                                event.preventDefault();

                                stripe.createToken(card).then(function(result) {
                                    if (result.error) {
                                        // Inform the user if there was an error.
                                        var errorElement = document.getElementById('card-errors');
                                        errorElement.textContent = result.error.message;
                                    } else {
                                        // Send the token to your server.
                                        stripeTokenHandler(result.token);
                                    }
                                });
                            });

                            // Submit the form with the token ID.
                            function stripeTokenHandler(token) {
                                // Insert the token ID into the form so it gets submitted to the server
                                var form = document.getElementById('payment-form');
                                var hiddenInput = document.createElement('input');
                                hiddenInput.setAttribute('type', 'hidden');
                                hiddenInput.setAttribute('name', 'stripeToken');
                                hiddenInput.setAttribute('value', token.id);
                                form.appendChild(hiddenInput);

                                // Submit the form
                                form.submit();
                            }
                        </script>

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide">
                                {{ __('Register') }}
                            </button>
                        </div>
                    </div>
            </form>
        </div>
    </div>
    <div class="col-md-5 col-sm-12 first-xs last-md">
        <div class="row">
            @include('auth.register.cards.hobby')
        </div>
    </div>
</div>
@endsection
