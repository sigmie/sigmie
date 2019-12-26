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
                    <div class="col-md-4 col-sm-4 col-xs-4  col-xs-offset-4 col-md-offset-4 flex flex-col justify-center">
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
            <div class="bg-indigo-300">
                <div class="container mx-auto bg-red-300 h-full">
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            lorem ispum
                        </div>
                        <div class="col-lg-12 text-center">
                            Lorem ipsum dolor sit amet consectetur adipisicing elit. Expedita numquam nulla quisquam
                        </div>
                        <div class="col-lg-4 text-center">
                            <img class="mx-auto"
                                src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                                width="200">
                            <h4>Keep your finance</h4>
                            <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Dolorem corporis, rem itaque
                                quis
                                velit saepe dolores amet vitae, nesciunt ipsam tenetur qui? Animi, quia nulla.
                                Perferendis
                                id aut saepe consequuntur!</p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <img class="mx-auto"
                                src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                                width="200">
                            <h4>Keep your finance</h4>
                            <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Dolorem corporis, rem itaque
                                quis
                                velit saepe dolores amet vitae, nesciunt ipsam tenetur qui? Animi, quia nulla.
                                Perferendis
                                id aut saepe consequuntur!</p>
                        </div>
                        <div class="col-lg-4 text-center">
                            <img class="mx-auto"
                                src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                                width="200">
                            <h4>Keep your finance</h4>
                            <p>Lorem ipsum dolor sit, amet consectetur adipisicing elit. Dolorem corporis, rem itaque
                                quis
                                velit saepe dolores amet vitae, nesciunt ipsam tenetur qui? Animi, quia nulla.
                                Perferendis
                                id aut saepe consequuntur!</p>
                        </div>
                    </div>

                </div>
                <div class="bg-blue-300">
                    <div class="container mx-auto">
                        <div class="row">
                            <div class="col-lg-6">
                                <h5>Title</h5>
                                <p>Lorem ipsum, dolor sit amet consectetur adipisicing elit. Exercitationem aperiam
                                    asperiores, culpa sequi alias tempora at veritatis. Commodi voluptates quasi officia
                                    explicabo, placeat veniam fugit consequatur labore cupiditate a neque.</p>
                            </div>
                            <div class="col-lg-6">
                                <img class="mx-auto"
                                    src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                                    width="200">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-red-300">
                    <div class="container mx-auto">
                        <div class="row">
                            <div class="col-lg-6">
                                <img class="mx-auto"
                                    src="https://res.cloudinary.com/markos-nikolaos-orfanos/image/upload/v1574659602/Group_2_fxapdw.png"
                                    width="200">
                            </div>
                            <div class="col-lg-6">
                                <h5>Title</h5>
                                <p>Lorem ipsum, dolor sit amet consectetur adipisicing elit. Exercitationem aperiam
                                    asperiores, culpa sequi alias tempora at veritatis. Commodi voluptates quasi officia
                                    explicabo, placeat veniam fugit consequatur labore cupiditate a neque.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-yello-300 text-center">
                    <h3>Start saving money</h3>
                    <div class="inline-block">
                        <div class="inline-block">
                            Button 1
                        </div>
                        <div class="inline-block">
                            Button 2
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
