<?php
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="auto">

<head>
   <meta charset="utf-8">
   <meta name="viewport" content="width=device-width, initial-scale=1">
   <meta name="description" content="">
   <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
   <meta name="generator" content="Astro v5.13.2">
   <title>Album example · Bootstrap v5.3</title>
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
      <header class="d-flex flex-wrap align-items-center justify-content-center justify-content-md-between py-3 mb-4 border-bottom">
         <div class="col-md-3 mb-2 mb-md-0">
            <h1>EventHub</h1>
         </div>
         <ul class="nav col-12 col-md-auto mb-2 justify-content-center mb-md-0">
            <li><a href="#" class="nav-link px-2 link-secondary">Home</a></li>
            <li><a href="#" class="nav-link px-2">Features</a></li>
            <li><a href="#" class="nav-link px-2">Pricing</a></li>
            <li><a href="#" class="nav-link px-2">FAQs</a></li>
            <li><a href="#" class="nav-link px-2">About</a></li>
         </ul>
         <div class="col-md-3 text-end"> <button type="button" class="btn btn-outline-primary me-2">Login</button> <button type="button" class="btn btn-primary">Sign-up</button> </div>
      </header>
   </div>
   <main>
      <section class="py-5 text-center container">
         <div class="row py-lg-5">
            <div class="col-lg-6 col-md-8 mx-auto">
               <h1 class="fw-light">Album example</h1>
               <p class="lead text-body-secondary">Something short and leading about the collection below—its contents, the creator, etc. Make it short and sweet, but not too short so folks don’t simply skip over it entirely.</p>
               <p> <a href="#" class="btn btn-primary my-2">Main call to action</a> <a href="#" class="btn btn-secondary my-2">Secondary action</a> </p>
            </div>
         </div>
      </section>
      <div class="album py-5 bg-body-tertiary">
         <div class="container">
            <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
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
   <footer class="text-body-secondary py-5">
      <div class="container">
         <p class="float-end mb-1"> <a href="#">Back to top</a> </p>
         <p class="mb-1">Album example is &copy; Bootstrap, but please download and customize it for yourself!</p>
         <p class="mb-0">New to Bootstrap? <a href="/">Visit the homepage</a> or read our <a href="/docs/5.3/getting-started/introduction">getting started guide</a>.</p>
      </div>
   </footer>
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>

</html>