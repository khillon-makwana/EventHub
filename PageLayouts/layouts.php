<?php
class layouts
{
    public function head($conf)
    {
?>
        <!DOCTYPE html>
        <html lang="en" data-bs-theme="auto">

        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="description" content="">
            <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
            <meta name="generator" content="Astro v5.13.2">
            <title><?php echo $conf['site_name']; ?></title>
            <link rel="canonical" href="https://getbootstrap.com/docs/5.3/examples/album/">
            <script src="/docs/5.3/assets/js/color-modes.js"></script>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">

            <style>
                .bd-placeholder-img {
                    font-size: 1.125rem;
                    text-anchor: middle;
                    -webkit-user-select: none;
                    -moz-user-select: none;
                    user-select: none
                }

                @media (min-width: 768px) {
                    .bd-placeholder-img-lg {
                        font-size: 3.5rem
                    }
                }

                .b-example-divider {
                    width: 100%;
                    height: 3rem;
                    background-color: #0000001a;
                    border: solid rgba(0, 0, 0, .15);
                    border-width: 1px 0;
                    box-shadow: inset 0 .5em 1.5em #0000001a, inset 0 .125em .5em #00000026
                }

                .b-example-vr {
                    flex-shrink: 0;
                    width: 1.5rem;
                    height: 100vh
                }

                .bi {
                    vertical-align: -.125em;
                    fill: currentColor
                }

                .nav-scroller {
                    position: relative;
                    z-index: 2;
                    height: 2.75rem;
                    overflow-y: hidden
                }

                .nav-scroller .nav {
                    display: flex;
                    flex-wrap: nowrap;
                    padding-bottom: 1rem;
                    margin-top: -1px;
                    overflow-x: auto;
                    text-align: center;
                    white-space: nowrap;
                    -webkit-overflow-scrolling: touch
                }

                .btn-bd-primary {
                    --bd-violet-bg: #712cf9;
                    --bd-violet-rgb: 112.520718, 44.062154, 249.437846;
                    --bs-btn-font-weight: 600;
                    --bs-btn-color: var(--bs-white);
                    --bs-btn-bg: var(--bd-violet-bg);
                    --bs-btn-border-color: var(--bd-violet-bg);
                    --bs-btn-hover-color: var(--bs-white);
                    --bs-btn-hover-bg: #6528e0;
                    --bs-btn-hover-border-color: #6528e0;
                    --bs-btn-focus-shadow-rgb: var(--bd-violet-rgb);
                    --bs-btn-active-color: var(--bs-btn-hover-color);
                    --bs-btn-active-bg: #5a23c8;
                    --bs-btn-active-border-color: #5a23c8
                }

                .bd-mode-toggle {
                    z-index: 1500
                }

                .bd-mode-toggle .bi {
                    width: 1em;
                    height: 1em
                }

                .bd-mode-toggle .dropdown-menu .active .bi {
                    display: block !important
                }
            </style>
        </head>

        <body>
            <div class="container">
            <?php
        }

        public function header($conf)
        {
            ?>
                <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
                    <div class="col-md-3 mb-2 mb-md-0">
                        <h1><?php echo $conf['site_name']; ?></h1>
                    </div>
                    <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
                        <li><a href="#" class="nav-link px-2 link-secondary">Home</a></li>
                        <li><a href="#" class="nav-link px-2">About</a></li>
                    </ul>
                    <div class="col-md-3 text-end"> <a href="signout.php" class="btn btn-primary">Sign Out</a>
                    </div>
                </header>
            </div>
        <?php
        }

        public function banner($FlashMessageObject)
        {
        ?>
            <main>
                <section class="py-2 text-center container">
                    <div class="container mt-5">
                        <?php echo $FlashMessageObject->getMsg('msg'); ?>
                        <h2>Welcome, <?php echo htmlspecialchars($_SESSION['user_fullname'] ?? ''); ?>!</h2>
                        <p>EventHub is a platform where you can see events happening and be able to rsvp to events you are interested in.</p>
                        <!--<p><a href="signout.php">Sign out</a></p> -->
                    </div>
                </section>
                <div class="album py-5 bg-body-tertiary">
                    <div class="container">
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                        <?php
                    }

                    public function events()
                    {
                        ?>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="card shadow-sm">
                                    <svg aria-label="Placeholder: Thumbnail" class="bd-placeholder-img card-img-top" height="225" preserveAspectRatio="xMidYMid slice" role="img" width="100%" xmlns="http://www.w3.org/2000/svg">
                                        <title>Placeholder</title>
                                        <rect width="100%" height="100%" fill="#55595c"></rect>
                                        <text x="50%" y="50%" fill="#eceeef" dy=".3em">Thumbnail</text>
                                    </svg>
                                    <div class="card-body">
                                        <p class="card-text">This is a wider card with supporting text below as a natural lead-in to additional content. This content is a little bit longer.</p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="btn-group"> <button type="button" class="btn btn-sm btn-outline-secondary">View</button> <button type="button" class="btn btn-sm btn-outline-secondary">Edit</button> </div>
                                            <small class="text-body-secondary">9 mins</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        <?php
                    }

                    public function form_content($conf, $FormObject, $FlashMessageObject)
                    {
        ?>
            <!-- Outer container with a smooth gradient background -->
            <div class="d-flex justify-content-center align-items-center vh-100">

                <!-- Card container with a subtle shadow and rounded edges -->
                <div class="col-md-6 col-lg-5">
                    <div class="h-100 p-5 rounded-4 shadow-lg text-white"
                        style="background: linear-gradient(160deg, #84898cff, #2c3e50);">

                        <!-- Optional header -->
                        <h2 class="mb-4 text-center fw-bold">Welcome to EventHub</h2>

                        <?php
                        $page = basename($_SERVER['PHP_SELF']);

                        if ($page == 'signup.php') {
                            $FormObject->signup($conf, $FlashMessageObject);
                        } elseif ($page == 'signin.php') {
                            $FormObject->signin($conf, $FlashMessageObject);
                        }
                        ?>


                    </div>
                </div>
            </div>

        <?php
                    }

                    public function footer($conf)
                    {
        ?>
            <footer class="text-body-secondary py-5">
                <div class="container">
                    <p class="mb-1"> &copy; EventHub. All Rights Reserved</p>
                </div>
            </footer>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
        </body>

        </html>
<?php
                    }
                }
