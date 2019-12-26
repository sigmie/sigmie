<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full bg-gray-100">

    @include('cookieConsent::index')

    <div id="app" class="min-h-full relative">

        <div style="background-image: linear-gradient(45deg, #667EEA 0%, #7F9CF5 45%, #A3BFFA 100%);">
            <nav class="container mx-auto">
                <div class="m-0 p-3 row flex h-full m-auto">
                    <div class="col-md-4 col-md-4 col-sm-4 col-xs-4 align-middle">
                    </div>
                    <div
                        class="col-md-4 col-sm-4 col-xs-4  col-xs-offset-4 col-md-offset-4 flex flex-col justify-center">
                        <div class="flex justify-end">
                            <a class="flex items-center" href="{{ route('login') }}">Sign in
                                <icon-cheveron-right />
                            </a>
                        </div>
                    </div>
                </div>
            </nav>
            <div class="container w-full mx-auto py-8">
                <div class="row mx-auto w-full h-full">
                    <div class="col-lg-6 col-md-7 col-sm-12 col-xs-12 first-lg first-md">
                        <div class="row text-primary-text tracking-wide">
                            <div class="col-lg-12 pt-8 lg:pt-16 px-6">
                                <h1 class="text-5xl font-semibold tracking-wide text-primary">
                                    Awesome search, your infastructure.
                                </h1>
                            </div>
                            <div class="col-lg-12 text-primary pt-4 px-6">
                                <p class="text-xl">
                                    Beautifully designed components and templates, hand-crafted using the CSS framework
                                    you already know and love.
                                </p>
                                <p class="text-xl font-medium pt-8">
                                    Sign up for project updates, early previews, and to find out when it’s ready.
                                </p>
                            </div>
                            <div class="inline-block pt-4 w-full px-6">
                                <div class="inline-block w-full sm:w-auto">
                                    <input type="text" placeholder="Email address"
                                        class="block w-full sm:max-w-xs px-5 py-2 text-lg leading-snug appearance-none bg-white rounded-lg focus:outline-none focus:shadow-outline">
                                </div>
                                <div class="inline-block w-full sm:w-auto">
                                    <button
                                        class="mt-4 relative w-full sm:mt-0 sm:h-auto sm:ml-4 block w-full sm:w-auto px-6 py-3 font-semibold leading-snug bg-primary text-white uppercase tracking-wide rounded-lg shadow-md focus:outline-none focus:shadow-outline hover:shadow">
                                        Subscribe
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-5 col-sm-12 col-xs-12 first-xs first-sm">
                        <svg class="mx-auto" width="533px" height="400px" viewBox="0 0 533 575" version="1.1"
                            xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                            <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                <g id="Desktop-HD" transform="translate(-907.000000, -160.000000)">
                                    <rect id="Rectangle" fill="url(#linearGradient-1)" x="0" y="0" width="1440"
                                        height="784"></rect>
                                    <g id="Group-6" opacity="0.388044085" transform="translate(907.000000, 160.000000)"
                                        fill="#FFFFFF">
                                        <path
                                            d="M101,169 C62.4033889,169 31,137.596611 31,99.0095655 C31,60.4033889 62.4033889,29 101,29 C139.596611,29 171,60.4033889 171,99.0095655 C171,137.596611 139.596611,169 101,169 L101,169 Z M101,63.7704291 C81.5821263,63.7704291 65.7704291,79.5821263 65.7704291,99.0095655 C65.7704291,118.427439 81.5821263,134.229571 101,134.229571 C120.427439,134.229571 136.229571,118.427439 136.229571,99.0095655 C136.229571,79.5821263 120.427439,63.7704291 101,63.7704291 L101,63.7704291 Z"
                                            id="Fill-1"></path>
                                        <path
                                            d="M436.004942,345 C408.982047,345 387,323.008069 387,295.995058 C387,268.972163 408.982047,247 436.004942,247 C463.017953,247 485,268.972163 485,295.995058 C485,323.008069 463.017953,345 436.004942,345 L436.004942,345 Z M436.004942,282.928391 C428.789612,282.928391 422.928391,288.789612 422.928391,295.995058 C422.928391,303.200504 428.789612,309.061725 436.004942,309.061725 C443.210388,309.061725 449.061725,303.200504 449.061725,295.995058 C449.061725,288.789612 443.210388,282.928391 436.004942,282.928391 L436.004942,282.928391 Z"
                                            id="Fill-2"></path>
                                        <path
                                            d="M285.530095,445 C195.911556,445 123,372.772808 123,283.995448 C123,195.218088 195.911556,123 285.530095,123 C361.942949,123 427.116727,174.480309 444.016696,248.200475 L411.438997,255.537898 C398.021947,196.984168 346.246839,156.091233 285.530095,156.091233 C214.337024,156.091233 156.413967,213.470612 156.413967,283.995448 C156.413967,354.529388 214.337024,411.908767 285.530095,411.908767 C347.083107,411.908767 400.356146,368.557886 412.210936,308.848011 L445,315.229567 C430.06664,390.4245 363.008961,445 285.530095,445"
                                            id="Fill-3"></path>
                                        <path
                                            d="M337,300 C337,324.85573 316.857805,345 292.004637,345 C267.151468,345 247,324.85573 247,300 C247,275.14427 267.151468,255 292.004637,255 C316.857805,255 337,275.14427 337,300"
                                            id="Fill-4"></path>
                                        <path
                                            d="M287.504615,575 C128.969547,575 0,446.021222 0,287.495385 C0,223.31522 20.6949416,162.59652 59.8510266,111.90222 L99.6809433,142.667715 C67.4016342,184.463744 50.3343072,234.548826 50.3343072,287.495385 C50.3343072,418.264893 156.725876,524.665693 287.504615,524.665693 C418.274124,524.665693 524.665693,418.264893 524.665693,287.495385 C524.665693,156.716646 418.274124,50.3343072 287.504615,50.3343072 C244.093237,50.3343072 201.651068,62.1494389 164.756473,84.5151141 L138.652417,41.4729584 C183.420689,14.3443083 234.890357,0 287.504615,0 C446.030453,0 575,128.969547 575,287.495385 C575,446.021222 446.030453,575 287.504615,575"
                                            id="Fill-5"></path>
                                    </g>
                                </g>
                            </g>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <main>
            <div class="bg-primary-background">
                <div class="container mx-auto h-full">
                    <div class="px-6">
                        <div class="row py-20 text-primary">
                            <div
                                class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center pb-6 text-lg font-medium tracking-wide text-primary">
                                Latin professor at Hampden-Sydney
                            </div>
                            <div
                                class="col-lg-12 col-md-12 col-sm-12 col-xs-12 text-center text-center pb-12 font-medium tracking-normal text-3xl text-primary">
                                <div class="max-w-2xl mx-auto">
                                    It has roots in a piece of classical Latin literature from.
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 text-center px-4 lg:px-12">
                                <svg class="mx-auto" width="59px" height="60px" viewBox="0 0 59 60" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="Desktop-HD" transform="translate(-256.000000, -1143.000000)">
                                            <rect id="Rectangle" fill="#EBF8FF" x="0" y="784" width="1440" height="733">
                                            </rect>
                                            <circle id="Oval" stroke="#3C366B" stroke-width="2" cx="289" cy="1169"
                                                r="25"></circle>
                                            <circle id="Oval" fill="#3F3D56" fill-rule="nonzero" cx="281" cy="1178"
                                                r="25"></circle>
                                        </g>
                                    </g>
                                </svg>
                                <h4 class="text-2xl pt-4 pb-3">Latin professor Sydney</h4>
                                <p class="max-w-sm text-lg mx-auto pb-16 lg:pb-0">
                                    Its in a piece of classical Latin literature from 45 BC, making it over 2000 years o
                                </p>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 text-center px-4 lg:px-12">
                                <svg class="mx-auto" width="54px" height="55px" viewBox="0 0 54 55" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="Desktop-HD" transform="translate(-716.000000, -1157.000000)">
                                            <rect id="Rectangle" fill="#EBF8FF" x="0" y="784" width="1440" height="733">
                                            </rect>
                                            <g id="Group" transform="translate(716.000000, 1158.000000)"
                                                fill-rule="nonzero">
                                                <path
                                                    d="M45.7740795,44.6871933 C35.729346,55.3314483 18.9579119,55.8181378 8.31283867,45.7742794 C-2.33182115,35.7302166 -2.818239,18.9575268 7.22693732,8.31279893 C17.2712061,-2.33144177 34.0423736,-2.81813883 44.6870004,7.22571287 C55.3316626,17.270957 55.8180663,34.0424643 45.7740795,44.6871933 Z"
                                                    id="Path" fill="#3F3D56"></path>
                                                <path
                                                    d="M45.9104392,44.6872301 C41.0035434,50.0007015 34.1592297,53.0093601 27,53 L27,0 C33.6363552,-0.00945172078 40.0232914,2.57686053 44.8438742,7.2256861 C55.287662,17.2709472 55.7648868,34.0424829 45.9104392,44.6872301 Z"
                                                    id="Path" stroke="#3F3D56" fill="#EBF8FF"></path>
                                                <path
                                                    d="M11,36.5 C11,45.0604093 26,52 26,52 L26,21 C26,21 11,27.9395825 11,36.5 Z"
                                                    id="Path" fill="#EBF8FF"></path>
                                                <path
                                                    d="M27,21 L27,52 C27,52 42,45.0604093 42,36.5 C42,27.9395907 27,21 27,21 Z"
                                                    id="Path" fill="#3F3D56"></path>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                                <h4 class="text-2xl pt-4 pb-3">Latin professor Sydney</h4>
                                <p class="max-w-sm text-lg mx-auto pb-16 lg:pb-0">
                                    Its in a piece of classical Latin literature from 45 BC, making it over 2000 years o
                                </p>
                            </div>
                            <div class="col-lg-4 col-md-4 col-sm-12 col-xs-12 text-center px-4 lg:px-12">
                                <svg class="mx-auto" width="61px" height="60px" viewBox="0 0 61 60" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="Desktop-HD" transform="translate(-1073.000000, -1148.000000)">
                                            <rect id="Rectangle" fill="#EBF8FF" x="0" y="784" width="1440" height="733">
                                            </rect>
                                            <g id="Group-2" transform="translate(1073.000000, 1148.000000)"
                                                fill-rule="nonzero">
                                                <circle id="Oval" fill="#3F3D56" cx="30.5048819" cy="29.78906"
                                                    r="29.78906"></circle>
                                                <ellipse id="Oval" fill="#EBF8FF"
                                                    transform="translate(10.894530, 24.501438) rotate(-74.909930) translate(-10.894530, -24.501438) "
                                                    cx="10.89453" cy="24.5014375" rx="14.89453" ry="6.77024"></ellipse>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                                <h4 class="text-2xl pt-4 pb-3">Latin professor Sydney</h4>
                                <p class="max-w-sm text-lg mx-auto lg:pb-0">
                                    Its in a piece of classical Latin literature from 45 BC, making it over 2000 years o
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-white text-primary">
                    <div class="container mx-auto">
                        <div class="row py-24 px-6">
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 first-md first-lg">
                                <div class="mx-auto max-w-xl">
                                    <h5 class="font-medium text-5xl pb-4 max-w-lg">
                                        Keep your budget up to date
                                    </h5>
                                    <p class="text-xl max-w-lg">
                                        To control the background color of an element on hover, add the hover: prefix to
                                        any
                                        existing background color utility. For example.</p>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12 first-sm first-xs pb-12">
                                <svg class="mx-auto" width="444px" height="300px" viewBox="0 0 444 385" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="Desktop-HD" transform="translate(-821.000000, -1677.000000)">
                                            <g id="Group-4" transform="translate(-1.000000, 1517.000000)">
                                                <rect id="Rectangle" fill="#FFFFFF" x="0" y="0" width="1440"
                                                    height="669"></rect>
                                                <g id="Group-3" transform="translate(822.000000, 161.000000)">
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="0" y="0"
                                                        width="256" height="160"></rect>
                                                    <path
                                                        d="M167.889741,118 C183.619168,102.251826 188.324594,78.5678592 179.811889,57.9918861 C171.299184,37.415913 151.244778,24 129,24 L129,79.0639345 L167.889741,118 Z"
                                                        id="Path" fill="#3F3D56" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M128,24 C102.854343,24.0030157 80.9127052,41.1747363 74.7066299,65.7078656 C68.5005545,90.2409949 79.6105752,115.887551 101.691418,128 L128,79.3679813 L128,24 Z"
                                                        id="Path" fill="#718BEF" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M167,118.889866 L128.229585,80 L102,128.307575 C123.353678,139.961323 149.806814,136.128585 167,118.889866 L167,118.889866 Z"
                                                        id="Path" fill="#575A89" fill-rule="nonzero"></path>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="10"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="25"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="40"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="129" width="43" height="2"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="134" width="43" height="2"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="139" width="43" height="2"></rect>
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="79"
                                                        y="223" width="146" height="160"></rect>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="87"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="98"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="109"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="368" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="372" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="375" width="31" height="1"></rect>
                                                    <path
                                                        d="M152,251 L152,263.887301 C167.773571,263.887293 181.993978,273.306963 188.030265,287.753931 C194.066551,302.200898 190.729972,318.830079 179.57637,329.887307 L188.768477,339 C203.639939,324.25703 208.088708,302.084797 200.040332,282.822178 C191.991955,263.559559 173.031422,251 152,251 L152,251 Z"
                                                        id="Path" fill="#3F3D56" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M189,339.033049 L179.839082,330 C166.097082,343.552995 144.31304,345.009379 128.841482,333.409472 L121,343.583188 C141.63193,359.045344 170.675733,357.101913 189,339.033049 Z"
                                                        id="Path" fill="#575A89" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M113.002447,302.777328 C113.002447,281.330456 130.462246,263.944332 152,263.944332 L152,251 C129.679951,251 109.849697,265.184473 102.724122,286.247304 C95.5985468,307.310136 102.767451,330.551744 120.538015,344 L128.405943,333.691163 C118.694424,326.356086 112.993643,314.91498 113.002447,302.777328 L113.002447,302.777328 Z"
                                                        id="Path" fill="#7995F3" fill-rule="nonzero"></path>
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="296"
                                                        y="123" width="146" height="160"></rect>
                                                    <polyline id="Path" stroke="#3F3D56" stroke-width="2"
                                                        points="310 133 310 255 428 255"></polyline>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="317"
                                                        y="212" width="26" height="42"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="355"
                                                        y="183" width="26" height="71"></rect>
                                                    <rect id="Rectangle" fill="#8BA8F7" fill-rule="nonzero" x="393"
                                                        y="150" width="26" height="104"></rect>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="309"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="320"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="331"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="268" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="272" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="276" width="31" height="1"></rect>
                                                </g>
                                            </g>
                                            <g id="Group-4" transform="translate(0.000000, 1517.000000)">
                                                <rect id="Rectangle" fill="#FFFFFF" x="0" y="0" width="1440"
                                                    height="669"></rect>
                                                <g id="Group-3" transform="translate(822.000000, 161.000000)">
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="0" y="0"
                                                        width="256" height="160"></rect>
                                                    <path
                                                        d="M167.889741,118 C183.619168,102.251826 188.324594,78.5678592 179.811889,57.9918861 C171.299184,37.415913 151.244778,24 129,24 L129,79.0639345 L167.889741,118 Z"
                                                        id="Path" fill="#3F3D56" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M128,24 C102.854343,24.0030157 80.9127052,41.1747363 74.7066299,65.7078656 C68.5005545,90.2409949 79.6105752,115.887551 101.691418,128 L128,79.3679813 L128,24 Z"
                                                        id="Path" fill="#718BEF" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M167,118.889866 L128.229585,80 L102,128.307575 C123.353678,139.961323 149.806814,136.128585 167,118.889866 L167,118.889866 Z"
                                                        id="Path" fill="#575A89" fill-rule="nonzero"></path>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="10"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="25"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="40"
                                                        y="126" width="11" height="11"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="129" width="43" height="2"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="134" width="43" height="2"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="193"
                                                        y="139" width="43" height="2"></rect>
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="79"
                                                        y="223" width="146" height="160"></rect>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="87"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="98"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="109"
                                                        y="364" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="368" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="372" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="175"
                                                        y="375" width="31" height="1"></rect>
                                                    <path
                                                        d="M152,251 L152,263.887301 C167.773571,263.887293 181.993978,273.306963 188.030265,287.753931 C194.066551,302.200898 190.729972,318.830079 179.57637,329.887307 L188.768477,339 C203.639939,324.25703 208.088708,302.084797 200.040332,282.822178 C191.991955,263.559559 173.031422,251 152,251 L152,251 Z"
                                                        id="Path" fill="#3F3D56" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M189,339.033049 L179.839082,330 C166.097082,343.552995 144.31304,345.009379 128.841482,333.409472 L121,343.583188 C141.63193,359.045344 170.675733,357.101913 189,339.033049 Z"
                                                        id="Path" fill="#575A89" fill-rule="nonzero"></path>
                                                    <path
                                                        d="M113.002447,302.777328 C113.002447,281.330456 130.462246,263.944332 152,263.944332 L152,251 C129.679951,251 109.849697,265.184473 102.724122,286.247304 C95.5985468,307.310136 102.767451,330.551744 120.538015,344 L128.405943,333.691163 C118.694424,326.356086 112.993643,314.91498 113.002447,302.777328 L113.002447,302.777328 Z"
                                                        id="Path" fill="#7995F3" fill-rule="nonzero"></path>
                                                    <rect id="Rectangle" stroke="#3F3D56" stroke-width="2" x="296"
                                                        y="123" width="146" height="160"></rect>
                                                    <polyline id="Path" stroke="#3F3D56" stroke-width="2"
                                                        points="310 133 310 255 428 255"></polyline>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="317"
                                                        y="212" width="26" height="42"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="355"
                                                        y="183" width="26" height="71"></rect>
                                                    <rect id="Rectangle" fill="#8BA8F7" fill-rule="nonzero" x="393"
                                                        y="150" width="26" height="104"></rect>
                                                    <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="309"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="320"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#575A89" fill-rule="nonzero" x="331"
                                                        y="265" width="8" height="8"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="268" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="272" width="31" height="1"></rect>
                                                    <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="397"
                                                        y="276" width="31" height="1"></rect>
                                                </g>
                                            </g>
                                        </g>
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-primary-background text-primary">
                    <div class="container mx-auto">
                        <div class="row py-24 px-6">
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                <svg width="450px" height="284px" viewBox="0 0 559 284" version="1.1"
                                    xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                                    <g id="Homepage" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                                        <g id="Desktop-HD" transform="translate(-89.000000, -2336.000000)">
                                            <rect id="Rectangle" fill="#EBF8FF" x="0" y="2186" width="1440"
                                                height="601"></rect>
                                            <g id="undraw_server_down_s4lk"
                                                transform="translate(89.000000, 2336.000000)">
                                                <path
                                                    d="M63.7858647,39.0214952 C54.9862974,39.0956925 43.3395897,37.5890476 40.9951407,36.0965743 C39.2097226,34.9599963 38.4982522,30.8816046 38.2601988,29 C38.0953366,29.0073975 38,29.0105679 38,29.0105679 C38,29.0105679 38.4936613,35.5799782 40.8381103,37.0724515 C43.1825593,38.5649248 54.829262,40.0715644 63.6288343,39.9973672 C66.1689165,39.9759566 67.0463109,39.0314978 66.9981319,37.6326614 C66.6452306,38.4778347 65.6764636,39.0055641 63.7858647,39.0214952 Z"
                                                    id="Path" fill="#000000" fill-rule="nonzero" opacity="0.2"></path>
                                                <ellipse id="Oval" fill="#3F3D56" fill-rule="nonzero" cx="94" cy="146"
                                                    rx="94" ry="13"></ellipse>
                                                <ellipse id="Oval" fill="#000000" fill-rule="nonzero" opacity="0.1"
                                                    cx="94" cy="146.5" rx="79" ry="10.5"></ellipse>
                                                <ellipse id="Oval" fill="#3F3D56" fill-rule="nonzero" cx="416.5"
                                                    cy="264.5" rx="142.5" ry="19.5"></ellipse>
                                                <path
                                                    d="M151,63.8592972 C151,63.8592972 225.672608,37.5469903 230.267845,122.33109 C234.863083,207.11519 174.531669,222.743093 226.22809,239.553733 C242.328238,242.742909 253.920563,244.558331 261.005067,245 C264.249599,245.202274 276.66178,245.189372 279.5,245 C295.039154,243.963194 317.161913,242.147772 345.868276,239.553733 C353.031144,238.294317 357.235106,236.020574 358.48016,232.732503 C359.725215,229.444433 358.733771,223.234226 355.505827,214.101883"
                                                    id="Path" stroke="#2F2E41" stroke-width="2"></path>
                                                <path
                                                    d="M386.79082,215.498636 L382.787936,240.376232 C382.787936,240.376232 363.081384,250.567054 376.937554,250.866786 C390.793725,251.166518 456.071672,250.866786 456.071672,250.866786 C456.071672,250.866786 468.696185,250.866786 448.681717,240.0765 L444.678822,214 L386.79082,215.498636 Z"
                                                    id="Path" fill="#2F2E41" fill-rule="nonzero"></path>
                                                <path
                                                    d="M374.146026,250.940013 C377.293079,248.188841 382.784237,245.322228 382.784237,245.322228 L386.787321,220.211347 L444.678057,220.264854 L448.681141,245.019655 C453.348618,247.559496 456.240014,249.506594 457.931547,251 C460.502252,250.423733 463.311549,248.282209 448.681141,240.321011 L444.678057,214 L386.787321,215.512703 L382.784257,240.623568 C382.784257,240.623568 366.224167,249.268237 374.146026,250.940013 Z"
                                                    id="Path" fill="#000000" fill-rule="nonzero" opacity="0.1"></path>
                                                <rect id="Rectangle" fill="#2F2E41" fill-rule="nonzero" x="287" y="39"
                                                    width="258" height="180" rx="18.04568"></rect>
                                                <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="295" y="49"
                                                    width="241" height="134"></rect>
                                                <circle id="Oval" fill="#F2F2F2" fill-rule="nonzero" cx="416" cy="45"
                                                    r="2"></circle>
                                                <path
                                                    d="M545,194 L545,209.865023 C545,212.287863 544.044694,214.611557 542.343462,216.324764 C540.64223,218.03797 538.334777,219.000305 535.928871,219 L296.071134,219 C293.665227,219.000306 291.357773,218.037972 289.65654,216.324765 C287.955306,214.611559 287,212.287864 287,209.865023 L287,194 L545,194 Z"
                                                    id="Path" fill="#2F2E41" fill-rule="nonzero"></path>
                                                <polygon id="Path" fill="#2F2E41" fill-rule="nonzero"
                                                    points="483 267.956413 483 271 318 271 318 268.56513 318.227248 267.956413 322.293917 257 479.626208 257">
                                                </polygon>
                                                <path
                                                    d="M545.911844,265.396744 C545.607472,266.661895 544.458278,267.996522 541.861729,269.258629 C532.543951,273.787805 513.597836,268.05085 513.597836,268.05085 C513.597836,268.05085 499,265.635289 499,259.29445 C499.409883,259.021589 499.837303,258.774501 500.27964,258.554697 C504.197085,256.539868 517.18608,251.568103 540.216923,258.765214 C541.913627,259.282966 543.429163,260.248481 544.594705,261.554202 C545.52798,262.616271 546.259712,263.96345 545.911844,265.396744 Z"
                                                    id="Path" fill="#2F2E41" fill-rule="nonzero"></path>
                                                <path
                                                    d="M545.915161,265.837014 C534.939392,270.265307 525.156204,270.595741 515.115968,263.253064 C510.052508,259.551825 505.452353,258.635949 502,258.705219 C505.770039,256.605073 518.270286,251.422781 540.434528,258.92465 C542.067388,259.464326 543.525899,260.470725 544.647583,261.831736 C545.545741,262.938804 546.249939,264.343029 545.915161,265.837014 Z"
                                                    id="Path" fill="#000000" fill-rule="nonzero" opacity="0.1"></path>
                                                <ellipse id="Oval" fill="#F2F2F2" fill-rule="nonzero" cx="532" cy="262"
                                                    rx="4" ry="1"></ellipse>
                                                <circle id="Oval" fill="#F2F2F2" fill-rule="nonzero" cx="415" cy="207"
                                                    r="6"></circle>
                                                <polygon id="Path" fill="#000000" fill-rule="nonzero" opacity="0.1"
                                                    points="483 267 483 271 318 271 318 267.8 318.227248 267"></polygon>
                                                <rect id="Rectangle" fill="#2F2E41" fill-rule="nonzero" x="49" y="11"
                                                    width="105" height="122"></rect>
                                                <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="39" y="0"
                                                    width="126" height="43"></rect>
                                                <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="39" y="52"
                                                    width="126" height="43"></rect>
                                                <rect id="Rectangle" fill="#3F3D56" fill-rule="nonzero" x="39" y="103"
                                                    width="126" height="43"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.4"
                                                    x="131" y="7" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.8"
                                                    x="143" y="7" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="154" y="7"
                                                    width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.4"
                                                    x="131" y="59" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.8"
                                                    x="143" y="59" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="154" y="59"
                                                    width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.4"
                                                    x="131" y="109" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" opacity="0.8"
                                                    x="143" y="109" width="8" height="8"></rect>
                                                <rect id="Rectangle" fill="#6C63FF" fill-rule="nonzero" x="154" y="109"
                                                    width="8" height="8"></rect>
                                            </g>
                                        </g>
                                    </g>
                                </svg>

                            </div>
                            <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                                <div class="mx-auto max-w-xl">
                                    <h5 class="font-medium text-5xl pb-4 max-w-lg">
                                        Keep your budget up to date
                                    </h5>
                                    <p class="text-xl max-w-lg">
                                        To control the background color of an element on hover, add the hover: prefix to
                                        any
                                        existing background color utility. For example.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white text-center text-primary">
                    <h3 class="text-xl font-semibold pt-6">Sign up for project updates</h3>
                    <div class="inline-block pt-4 w-full px-6 pb-8">
                        <div class="inline-block w-full sm:max-w-xs">
                            <input
                                class="bg-white focus:outline-none focus:shadow-outline border border-gray-300 rounded-lg py-2 px-4 block w-full appearance-none leading-normal"
                                type="email" placeholder="Email address">
                        </div>
                        <div class="inline-block w-full sm:w-auto">
                            <button
                                class="mt-4 relative w-full sm:mt-0 sm:h-auto sm:ml-4 block w-full sm:w-auto px-6 py-3 font-semibold leading-snug bg-primary text-white uppercase tracking-wide rounded-lg shadow-md focus:outline-none focus:shadow-outline hover:shadow">

                                Subscribe
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>


    @if(config('app.env') == 'local')
    <script src="http://localhost:35729/livereload.js"></script>
    @endif

    @include('common.analytics')
</body>

</html>
