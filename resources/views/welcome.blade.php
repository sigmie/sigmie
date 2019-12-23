<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full min-h-full">

<head>
    @include('common.head')
</head>

<body class="min-h-full h-full bg-gray-100">

    @include('cookieConsent::index')

    <div id="app" class="min-h-full relative pb-20">


        <div style="background-image: linear-gradient(45deg, #667EEA 0%, #7F9CF5 45%, #A3BFFA 100%);">
            <nav class="container mx-auto">
                @include('common.navbar')
            </nav>
            <div class="container bg-red-900 w-full mx-auto">
                <div class="row mx-auto bg-yellow-500 w-full h-full">
                    <div class="col-lg-7 bg-green-500">
                        <div class="row">
                            <div class="col-lg-12">
                                Title
                            </div>
                            <div class="col-lg-12">
                                descirption text
                            </div>
                            <div class="col-lg-12">
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
                    </div>
                    <div class="col-lg-5 bg-red-500">

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
