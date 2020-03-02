@extends('layouts.public')

@section('additional-js')
<script src="https://js.stripe.com/v3/" type="application/javascript"></script>
@endsection

@section('additional-css')
@endsection()


@section('js-assign')
var stripe = @json($stripe);
@endsection()


@section('public.content')

<div class="flex h-full items-center pt-5">
    <div class="flex flex-wrap md:flex-no-wrap flex-wrap-reverse">
        <div class="flex-initial w-full md:w-2/5 px-4 py-2 m-2">
            <container-white>
                <register-form id="register" method="POST" action="{{ route('register') }}"
                    :errors="{{ json_encode($errors->getMessages()) }} "
                    :old="{{ json_encode(Session::getOldInput()) }}" />
            </container-white>
        </div>
        <div class="flex-initial w-full md:w-3/5 text-gray-700 text-center bg-gray-400 px-4 py-2 m-2">

            <container-white>
                <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Applicant Information
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                        Personal details and application.
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-0">
                    <dl>
                        <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                Full name
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                Margot Foster
                            </dt>
                        </div>
                        <div
                            class="mt-8 sm:mt-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-t sm:border-gray-200 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                Application for
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                Backend Developer
                            </dt>
                        </div>
                        <div
                            class="mt-8 sm:mt-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-t sm:border-gray-200 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                Email address
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                margotfoster@example.com
                            </dt>
                        </div>
                        <div
                            class="mt-8 sm:mt-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-t sm:border-gray-200 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                Salary expectation
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                $120,000
                            </dt>
                        </div>
                        <div
                            class="mt-8 sm:mt-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-t sm:border-gray-200 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                About
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                Fugiat ipsum ipsum deserunt culpa aute sint do nostrud anim incididunt cillum culpa
                                consequat.
                                Excepteur qui ipsum aliquip consequat sint. Sit id mollit nulla mollit nostrud in ea
                                officia
                                proident. Irure nostrud pariatur mollit ad adipisicing reprehenderit deserunt qui eu.
                            </dt>
                        </div>
                        <div
                            class="mt-8 sm:mt-0 sm:grid sm:grid-cols-3 sm:gap-4 sm:border-t sm:border-gray-200 sm:px-6 sm:py-5">
                            <dd class="text-sm leading-5 font-medium text-gray-500">
                                Attachments
                            </dd>
                            <dt class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                <ul class="border border-gray-200 rounded-md">
                                    <li class="pl-3 pr-4 py-3 flex items-center justify-between text-sm leading-5">
                                        <div class="w-0 flex-1 flex items-center">
                                            <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            <span class="ml-2 truncate">
                                                resume_back_end_developer.pdf
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <a href="#"
                                                class="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 ease-in-out">
                                                Download
                                            </a>
                                        </div>
                                    </li>
                                    <li
                                        class="border-t border-gray-200 pl-3 pr-4 py-3 flex items-center justify-between text-sm leading-5">
                                        <div class="w-0 flex-1 flex items-center">
                                            <svg class="flex-shrink-0 h-5 w-5 text-gray-400" fill="currentColor"
                                                viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M8 4a3 3 0 00-3 3v4a5 5 0 0010 0V7a1 1 0 112 0v4a7 7 0 11-14 0V7a5 5 0 0110 0v4a3 3 0 11-6 0V7a1 1 0 012 0v4a1 1 0 102 0V7a3 3 0 00-3-3z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            <span class="ml-2 truncate">
                                                coverletter_back_end_developer.pdf
                                            </span>
                                        </div>
                                        <div class="ml-4 flex-shrink-0">
                                            <a href="#"
                                                class="font-medium text-indigo-600 hover:text-indigo-500 transition duration-150 ease-in-out">
                                                Download
                                            </a>
                                        </div>
                                    </li>
                                </ul>
                            </dt>
                        </div>
                    </dl>
                </div>
            </container-white>
        </div>
    </div>
</div>

@endsection
