@extends('layouts.app')
@section('content')
<div class="h-full mx-auto container m-12 w-9/12 xl:w-8/12">
    <div class="row">
        <div class="col-md-7 col-sm-12">
            <form method="POST" class="mx-auto flex container w-full text-gray-700 h-auto" action="{{ route('register') }}">
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
                                    <label for="name" class="pb-1 block">{{ __('Name') }}</label>
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

                        <div class="col-md-12 col-lg-12 col-sm-12 col-xs-12 px-10 py-4">
                            <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white text-sm py-2 px-4 rounded uppercase float-right font-semibold tracking-wide">
                                {{ __('Register') }}
                            </button>
                        </div>
                    </div>
            </form>
        </div>
    </div>
    <div class="col-md-5">
        <div class="row">
            <div class="col-md-12 border bg-white border-gray-400 rounded p-0 py-2">
                <h2 class="uppercase text-sm text-gray-500 px-8 py-2 font-semibold tracking-wider">
                    Hobby
                </h2>
                <div class="bg-green-200 px-6 text-center py-1">
                    <span class="text-green-600">
                        5-day Free trail
                    </span>
                </div>
                <div class="row py-3 m-0">
                    <div class="col-md-6 p-0 px-8">
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                1 Project
                            </span>
                        </div>
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                1 Cluster
                            </span>
                        </div>
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                2 Nodes
                            </span>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                Mail reports
                            </span>
                        </div>
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                Daily checks
                            </span>
                        </div>
                        <div class="py-1">
                            <svg class="fill-current inline-block relative mr-2" width="12px" height="12px" viewBox="0 0 20 15" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                <g id="Symbols" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                    <g id="Check-text-list" transform="translate(0.000000, -7.000000)" fill="#828282" fill-rule="nonzero">
                                        <g id="Group-4">
                                            <g id="icon-check-circle" transform="translate(0.000000, 7.000000)">
                                                <path d="M4.17028561,5.67006679 L7.46534157,8.92658167 L15.8297144,0.595962204 C16.8414129,-0.255187929 18.3404912,-0.187154822 19.2702752,0.752106182 C20.2000592,1.69136719 20.2470986,3.18521086 19.3782362,4.18065301 L9.23960247,14.2783736 C8.25401699,15.2405421 6.67666615,15.2405421 5.69108067,14.2783736 L0.621763804,9.22951329 C-0.247098598,8.23407114 -0.200059163,6.74022747 0.729724837,5.80096647 C1.65950884,4.86170546 3.15858708,4.79367236 4.17028561,5.64482249 L4.17028561,5.67006679 Z" id="Shape"></path>
                                            </g>
                                        </g>
                                    </g>
                                </g>
                            </svg>
                            <span class="text-sm">
                                More Feature
                            </span>
                        </div>
                    </div>
                </div>
                <div class="px-8 pb-2">
                    <div class="text-sm">
                        <span class="text-3xl font-semibold">€ 24</span> /Month
                    </div>
                    <div class="text-gray-500 text-sm">
                        Price does not include your GCP costs.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection