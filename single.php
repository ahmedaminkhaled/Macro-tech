<?php
require_once __DIR__ . '/config/app.php';

$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$reference = trim((string)($_GET['reference'] ?? ''));

$where = '';
$params = [];

if ($productId > 0) {
    $where = 'p.id = :id';
    $params[':id'] = $productId;
} elseif ($reference !== '') {
    $where = 'p.reference = :reference';
    $params[':reference'] = $reference;
}

if ($where === '') {
    redirect('404.php');
}

$stmtProduct = db()->prepare(
    "SELECT p.id, p.reference, p.designation, p.description, p.brand, p.price, p.quantity, p.photo_path,
            c.id AS category_id, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE {$where}
     LIMIT 1"
);

foreach ($params as $key => $value) {
    if ($key === ':id') {
        $stmtProduct->bindValue($key, (int)$value, PDO::PARAM_INT);
    } else {
        $stmtProduct->bindValue($key, (string)$value);
    }
}

$stmtProduct->execute();
$product = $stmtProduct->fetch();

if (!$product) {
    redirect('404.php');
}

$imagePath = !empty($product['photo_path']) ? (string)$product['photo_path'] : 'assets/img/product-3.png';
$pageTitle = trim((string)($product['designation'] ?? '')) !== ''
    ? (string)$product['designation'] . ' - MacroTech'
    : 'MacroTech - Product Details';
$pageDescription = trim((string)($product['description'] ?? '')) !== ''
    ? strip_tags((string)$product['description'])
    : 'Explore product details and specifications at MacroTech.';

$stmtRelated = db()->prepare(
    'SELECT id, designation, brand, price, quantity, photo_path
     FROM products
     WHERE category_id = :category_id AND id <> :id
    ORDER BY RAND()
    LIMIT 4'
);
$stmtRelated->bindValue(':category_id', (int)$product['category_id'], PDO::PARAM_INT);
$stmtRelated->bindValue(':id', (int)$product['id'], PDO::PARAM_INT);
$stmtRelated->execute();
$relatedProducts = $stmtRelated->fetchAll();

if (count($relatedProducts) < 4) {
    $relatedIds = array_map(static fn(array $item): int => (int)$item['id'], $relatedProducts);
    $relatedIds[] = (int)$product['id'];

    $placeholders = [];
    $fallbackParams = [];
    foreach ($relatedIds as $index => $relatedId) {
        $placeholder = ':related_' . $index;
        $placeholders[] = $placeholder;
        $fallbackParams[$placeholder] = $relatedId;
    }

    $remaining = 4 - count($relatedProducts);
    if ($remaining > 0) {
        $stmtFallbackRelated = db()->prepare(
            'SELECT id, designation, brand, price, quantity, photo_path
             FROM products
             WHERE id NOT IN (' . implode(', ', $placeholders) . ')
             ORDER BY RAND()
             LIMIT ' . $remaining
        );

        foreach ($fallbackParams as $placeholder => $relatedId) {
            $stmtFallbackRelated->bindValue($placeholder, $relatedId, PDO::PARAM_INT);
        }

        $stmtFallbackRelated->execute();
        $relatedProducts = array_merge($relatedProducts, $stmtFallbackRelated->fetchAll());
    }
}

$stmtFeatured = db()->prepare(
    'SELECT id, designation, price, photo_path
     FROM products
     WHERE id <> :id
     ORDER BY RAND()
     LIMIT 6'
);
$stmtFeatured->bindValue(':id', (int)$product['id'], PDO::PARAM_INT);
$stmtFeatured->execute();
$featuredProducts = $stmtFeatured->fetchAll();

$searchCategories = [];
try {
    $stmtCategories = db()->query('SELECT id, name FROM categories ORDER BY name ASC');
    $searchCategories = $stmtCategories->fetchAll();
} catch (Throwable $e) {
    $searchCategories = [];
}

$stockQuantity = (int)$product['quantity'];
if ($stockQuantity <= 0) {
    $stockLabel = 'Out of stock';
    $stockBadgeClass = 'bg-danger';
} elseif ($stockQuantity <= 5) {
    $stockLabel = 'Low stock';
    $stockBadgeClass = 'bg-warning text-dark';
} else {
    $stockLabel = 'In stock';
    $stockBadgeClass = 'bg-success';
}

$tagLinks = [];
$tagLinks[] = [
    'label' => $product['brand'],
    'q' => $product['brand'],
    'category_id' => (int)$product['category_id'],
];
$tagLinks[] = [
    'label' => $product['category_name'],
    'q' => $product['category_name'],
    'category_id' => (int)$product['category_id'],
];

$keywords = preg_split('/\s+/', trim((string)$product['designation'])) ?: [];
foreach (array_slice(array_values(array_filter(array_unique(array_map(static function (string $keyword): string {
    return trim($keyword, "\t\n\r\0\x0B,.-_");
}, $keywords)))), 0, 4) as $keyword) {
    if ($keyword !== '') {
        $tagLinks[] = [
            'label' => $keyword,
            'q' => $keyword,
            'category_id' => (int)$product['category_id'],
        ];
    }
}

$tagLinks = array_slice($tagLinks, 0, 6);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= e($pageTitle) ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="<?= e($pageDescription) ?>" name="description">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap"
        rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="assets/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="assets/lib/lightbox/css/lightbox.min.css" rel="stylesheet">


    <!-- Customized Bootstrap Stylesheet -->
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>

    <!-- Spinner Start -->
    <div id="spinner"
        class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="sr-only">Loading...</span>
        </div>
    </div>
    <!-- Spinner End -->


    <!-- Topbar Start -->
    <div class="container-fluid px-5 d-none border-bottom d-lg-block">
        <div class="row gx-0 align-items-center">
            <div class="col-lg-4 text-center text-lg-start mb-lg-0">
                <div class="d-inline-flex align-items-center" style="height: 45px;">
                    <a href="#" class="text-muted me-2"> Help</a><small> / </small>
                    <a href="#" class="text-muted mx-2"> Support</a><small> / </small>
                    <a href="#" class="text-muted ms-2"> Contact</a>

                </div>
            </div>
            <div class="col-lg-4 text-center d-flex align-items-center justify-content-center">
                <small class="text-dark">Call Us:</small>
                <a href="#" class="text-muted">20677687</a>
            </div>

            <div class="col-lg-4 text-center text-lg-end">
                <div class="d-inline-flex align-items-center" style="height: 45px;">
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle text-muted me-2" data-bs-toggle="dropdown"><small>
                                USD</small></a>
                        <div class="dropdown-menu rounded">
                            <a href="#" class="dropdown-item"> Euro</a>
                            <a href="#" class="dropdown-item"> Dollar</a>
                        </div>
                    </div>
                    
                    
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid px-5 py-4 d-none d-lg-block">
        <div class="row gx-0 align-items-center text-center">
            <div class="col-md-4 col-lg-3 text-center text-lg-start">
                <div class="d-inline-flex align-items-center">
                    <a href="index.php" class="navbar-brand p-0">
                        <h1 class="display-5 text-primary m-0"><i
                                class="fas fa-shopping-bag text-secondary me-2"></i>MacroTech</h1>
                        <!-- <img src="assets/img/logo.png" alt="Logo"> -->
                    </a>
                </div>
            </div>
            <div class="col-md-4 col-lg-6 text-center">
                <div class="position-relative ps-4">
                    <form class="d-flex border rounded-pill" method="get" action="shop.php">
                        <input class="form-control border-0 rounded-pill w-100 py-3" type="text" name="q"
                            placeholder="Search Looking For?">
                        <select class="form-select text-dark border-0 border-start rounded-0 p-3" name="category_id" style="width: 200px;">
                            <option value="0">All Category</option>
                            <?php foreach ($searchCategories as $cat): ?>
                                <option value="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary rounded-pill py-3 px-5" style="border: 0;"><i
                                class="fas fa-search"></i></button>
                    </form>
                </div>
            </div>
            <div class="col-md-4 col-lg-3 text-center text-lg-end">
                <div class="d-inline-flex align-items-center">
                    <a href="#" class="text-muted d-flex align-items-center justify-content-center me-3"><span
                            class="rounded-circle btn-md-square border"><i class="fas fa-random"></i></i></a>
                    <a href="#" class="text-muted d-flex align-items-center justify-content-center me-3"><span
                            class="rounded-circle btn-md-square border"><i class="fas fa-heart"></i></a>
                    <a href="cheackout.php" class="text-muted d-flex align-items-center justify-content-center"><span
                            class="rounded-circle btn-md-square border"><i class="fas fa-shopping-cart"></i></span>
                        <span class="text-dark ms-2">$0.00</span></a>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->

    <!-- Navbar & Hero Start -->
    <div class="container-fluid nav-bar p-0">
        <div class="row gx-0 bg-primary px-5 align-items-center">
            <div class="col-lg-3 d-none d-lg-block">
                <nav class="navbar navbar-light position-relative" style="width: 250px;">
                    <button class="navbar-toggler border-0 fs-4 w-100 px-0 text-start" type="button"
                        data-bs-toggle="collapse" data-bs-target="#allCat">
                        <h4 class="m-0"><i class="fa fa-bars me-2"></i>All Categories</h4>
                    </button>
                    <div class="collapse navbar-collapse rounded-bottom" id="allCat">
                        <div class="navbar-nav ms-auto py-0">
                            <?php include __DIR__ . '/includes/sidebar_categories.php'; ?>
                        </div>
                    </div>
                </nav>
            </div>
            <div class="col-12 col-lg-9">
                <nav class="navbar navbar-expand-lg navbar-light bg-primary ">
                    <a href="" class="navbar-brand d-block d-lg-none">
                        <h1 class="display-5 text-secondary m-0"><i
                                class="fas fa-shopping-bag text-white me-2"></i>MacroTech</h1>
                        <!-- <img src="assets/img/logo.png" alt="Logo"> -->
                    </a>
                    <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse"
                        data-bs-target="#navbarCollapse">
                        <span class="fa fa-bars fa-1x"></span>
                    </button>
                    <div class="collapse navbar-collapse" id="navbarCollapse">
                        <div class="navbar-nav ms-auto py-0">
                            <a href="index.php" class="nav-item nav-link active">Home</a>
                            <a href="shop.php" class="nav-item nav-link">Shop</a>
                            <a href="single.php" class="nav-item nav-link">Single Page</a>
                            <a href="contact.php" class="nav-item nav-link me-2">Categories</a>
                            <div class="nav-item dropdown d-block d-lg-none mb-3">
                                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">All Category</a>
                                <div class="dropdown-menu m-0">
                                    <?php include __DIR__ . '/includes/sidebar_categories.php'; ?>
                                </div>
                            </div>
                        </div>
                        <a href="" class="btn btn-secondary rounded-pill py-2 px-4 px-lg-3 mb-3 mb-md-3 mb-lg-0"><i
                                class="fa fa-mobile-alt me-2"></i> 20677687</a>
                    </div>
                </nav>
            </div>
        </div>
    </div>
    <!-- Navbar & Hero End -->

    <!-- Single Page Header start -->
    <div class="container-fluid page-header py-5">
        <h1 class="text-center text-white display-6 wow fadeInUp" data-wow-delay="0.1s"><?= e($product['designation']) ?></h1>
        <ol class="breadcrumb justify-content-center mb-0 wow fadeInUp" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li class="breadcrumb-item active text-white"><?= e($product['designation']) ?></li>
        </ol>
    </div>
    <!-- Single Page Header End -->


    <!-- Single Products Start -->
    <div class="container-fluid shop py-5">
        <div class="container py-5">
            <div class="row g-4">
                <div class="col-lg-5 col-xl-3 wow fadeInUp" data-wow-delay="0.1s">
                    <div class="input-group w-100 mx-auto d-flex mb-4">
                        <input type="search" class="form-control p-3" placeholder="keywords"
                            aria-describedby="search-icon-1">
                        <span id="search-icon-1" class="input-group-text p-3"><i class="fa fa-search"></i></span>
                    </div>
                    <div class="product-categories mb-4">
                        <h4>Products Categories</h4>
                        <?php include __DIR__ . '/includes/sidebar_categories.php'; ?>
                    </div>
                    <div class="additional-product mb-4">
                        <h4>Select By Color</h4>
                        <div class="additional-product-item">
                            <input type="radio" class="me-2" id="Categories-1" name="Categories-1" value="Beverages">
                            <label for="Categories-1" class="text-dark"> Gold</label>
                        </div>
                        <div class="additional-product-item">
                            <input type="radio" class="me-2" id="Categories-2" name="Categories-1" value="Beverages">
                            <label for="Categories-2" class="text-dark"> Green</label>
                        </div>
                        <div class="additional-product-item">
                            <input type="radio" class="me-2" id="Categories-3" name="Categories-1" value="Beverages">
                            <label for="Categories-3" class="text-dark"> White</label>
                        </div>
                    </div>
                    <div class="featured-product mb-4">
                        <h4 class="mb-3">Featured products</h4>
                        <?php if (!empty($featuredProducts)): ?>
                            <?php foreach ($featuredProducts as $featuredProduct): ?>
                                <?php $featuredImage = !empty($featuredProduct['photo_path']) ? (string)$featuredProduct['photo_path'] : 'assets/img/product-3.png'; ?>
                                <div class="featured-product-item">
                                    <div class="rounded me-4" style="width: 100px; height: 100px;">
                                        <img src="<?= e($featuredImage) ?>" class="img-fluid rounded" alt="<?= e($featuredProduct['designation']) ?>">
                                    </div>
                                    <div>
                                        <h6 class="mb-2"><?= e($featuredProduct['designation']) ?></h6>
                                        <div class="d-flex mb-2">
                                            <i class="fa fa-star text-secondary"></i>
                                            <i class="fa fa-star text-secondary"></i>
                                            <i class="fa fa-star text-secondary"></i>
                                            <i class="fa fa-star text-secondary"></i>
                                            <i class="fa fa-star"></i>
                                        </div>
                                        <div class="d-flex mb-2">
                                            <h5 class="fw-bold me-2"><?= number_format((float)$featuredProduct['price'], 2) ?> $</h5>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-light border">No featured products available right now.</div>
                        <?php endif; ?>
                    </div>
                    <a href="#">
                        <div class="position-relative">
                            <img src="assets/img/product-banner-2.jpg" class="img-fluid w-100 rounded" alt="Image">
                            <div class="text-center position-absolute d-flex flex-column align-items-center justify-content-center rounded p-4"
                                style="width: 100%; height: 100%; top: 0; right: 0; background: rgba(242, 139, 0, 0.3);">
                                <h5 class="display-6 text-primary">SALE</h5>
                                <h4 class="text-secondary">Get UP To 50% Off</h4>
                                <a href="#" class="btn btn-primary rounded-pill px-4">Shop Now</a>
                            </div>
                        </div>
                    </a>
                    <div class="product-tags my-4">
                        <h4 class="mb-3">PRODUCT TAGS</h4>
                        <div class="product-tags-items bg-light rounded p-3">
                            <?php foreach ($tagLinks as $tagLink): ?>
                                <a href="shop.php?q=<?= urlencode((string)$tagLink['q']) ?>&category_id=<?= (int)$tagLink['category_id'] ?>"
                                    class="border rounded py-1 px-2 mb-2 d-inline-block"><?= e($tagLink['label']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7 col-xl-9 wow fadeInUp" data-wow-delay="0.1s">
                    <div class="row g-4 single-product">
                        <div class="col-xl-6">
                            <div class="single-carousel owl-carousel">
                                <div class="single-item" data-dot="<img class='img-fluid' src='<?= e($imagePath) ?>' alt=''>">
                                    <div class="single-inner bg-light rounded">
                                        <img src="<?= e($imagePath) ?>" class="img-fluid rounded" alt="<?= e($product['designation']) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-6">
                            <h4 class="fw-bold mb-3"><?= e($product['designation']) ?></h4>
                            <p class="mb-3">Category: <?= e($product['category_name']) ?></p>
                            <h5 class="fw-bold mb-3"><?= number_format((float)$product['price'], 2) ?> $</h5>
                            <div class="mb-3">
                                <span class="badge <?= e($stockBadgeClass) ?> rounded-pill px-3 py-2"><?= e($stockLabel) ?></span>
                                <span class="text-muted ms-2"><?= $stockQuantity ?> item<?= $stockQuantity === 1 ? '' : 's' ?> available</span>
                            </div>
                            <div class="d-flex mb-4">
                                <i class="fa fa-star text-secondary"></i>
                                <i class="fa fa-star text-secondary"></i>
                                <i class="fa fa-star text-secondary"></i>
                                <i class="fa fa-star text-secondary"></i>
                                <i class="fa fa-star"></i>
                            </div>
                            <div class="mb-3">
                                <div class="btn btn-primary d-inline-block rounded text-white py-1 px-4 me-2"><i
                                        class="fab fa-facebook-f me-1"></i> Share</div>
                                <div class="btn btn-secondary d-inline-block rounded text-white py-1 px-4 ms-2"><i
                                        class="fab fa-twitter ms-1"></i> Share</div>
                            </div>
                            <div class="d-flex flex-column mb-3">
                                <small>Product SKU: <?= e($product['reference']) ?></small>
                                <small>Availability: <strong class="text-primary"><?= e($stockLabel) ?></strong></small>
                            </div>
                            <p class="mb-4"><?= trim((string)$product['description']) !== '' ? e($product['description']) : 'No detailed description available for this product.' ?></p>
                            <p class="mb-4">Brand: <strong><?= e($product['brand']) ?></strong></p>
                            <div class="input-group quantity mb-5" style="width: 100px;">
                                <div class="input-group-btn">
                                    <button class="btn btn-sm btn-minus rounded-circle bg-light border">
                                        <i class="fa fa-minus"></i>
                                    </button>
                                </div>
                                <input type="text" class="form-control form-control-sm text-center border-0" value="1">
                                <div class="input-group-btn">
                                    <button class="btn btn-sm btn-plus rounded-circle bg-light border">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            <a href="#"
                                class="btn btn-primary border border-secondary rounded-pill px-4 py-2 mb-4 text-primary"><i
                                    class="fa fa-shopping-bag me-2 text-white"></i> Add to cart</a>
                        </div>
                        <div class="col-lg-12">
                            <nav>
                                <div class="nav nav-tabs mb-3">
                                    <button class="nav-link active border-white border-bottom-0" type="button"
                                        role="tab" id="nav-about-tab" data-bs-toggle="tab" data-bs-target="#nav-about"
                                        aria-controls="nav-about" aria-selected="true">Description</button>
                                </div>
                            </nav>
                            <div class="tab-content mb-5">
                                <div class="tab-pane active" id="nav-about" role="tabpanel"
                                    aria-labelledby="nav-about-tab">
                                    <p class="mb-3"><?= trim((string)$product['description']) !== '' ? e($product['description']) : 'No extended product description has been provided yet.' ?></p>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 h-100 bg-light">
                                                <h6 class="fw-bold mb-2">Brand</h6>
                                                <p class="mb-0"><?= e($product['brand']) ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 h-100 bg-light">
                                                <h6 class="fw-bold mb-2">Category</h6>
                                                <p class="mb-0"><?= e($product['category_name']) ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="border rounded p-3 h-100 bg-light">
                                                <h6 class="fw-bold mb-2">Availability</h6>
                                                <p class="mb-0"><?= e($stockLabel) ?> (<?= $stockQuantity ?>)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="nav-mission" role="tabpanel"
                                    aria-labelledby="nav-mission-tab">
                                    <div class="d-flex">
                                        <img src="assets/img/avatar.jpg" class="img-fluid rounded-circle p-3"
                                            style="width: 100px; height: 100px;" alt="">
                                        <div class="">
                                            <p class="mb-2" style="font-size: 14px;">April 12, 2024</p>
                                            <div class="d-flex justify-content-between">
                                                <h5>Jason Smith</h5>
                                                <div class="d-flex mb-3">
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star"></i>
                                                </div>
                                            </div>
                                            <p>The generated Lorem Ipsum is therefore always free from repetition
                                                injected humour, or non-characteristic
                                                words etc. Susp endisse ultricies nisi vel quam suscipit </p>
                                        </div>
                                    </div>
                                    <div class="d-flex">
                                        <img src="assets/img/avatar.jpg" class="img-fluid rounded-circle p-3"
                                            style="width: 100px; height: 100px;" alt="">
                                        <div class="">
                                            <p class="mb-2" style="font-size: 14px;">April 12, 2024</p>
                                            <div class="d-flex justify-content-between">
                                                <h5>Sam Peters</h5>
                                                <div class="d-flex mb-3">
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star text-secondary"></i>
                                                    <i class="fa fa-star"></i>
                                                    <i class="fa fa-star"></i>
                                                </div>
                                            </div>
                                            <p class="text-dark">The generated Lorem Ipsum is therefore always free from
                                                repetition injected humour, or non-characteristic
                                                words etc. Susp endisse ultricies nisi vel quam suscipit </p>
                                        </div>
                                    </div>
                                </div>
                                <div class="tab-pane" id="nav-vision" role="tabpanel">
                                    <p class="text-dark">Tempor erat elitr rebum at clita. Diam dolor diam ipsum et
                                        tempor sit. Aliqu diam
                                        amet diam et eos labore. 3</p>
                                    <p class="mb-0">Diam dolor diam ipsum et tempor sit. Aliqu diam amet diam et eos
                                        labore.
                                        Clita erat ipsum et lorem et sit</p>
                                </div>
                            </div>
                        </div>
                        <form action="#">
                            <h4 class="mb-5 fw-bold">Leave a Reply</h4>
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <div class="border-bottom rounded">
                                        <input type="text" class="form-control border-0 me-4" placeholder="Your Name *">
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="border-bottom rounded">
                                        <input type="email" class="form-control border-0" placeholder="Your Email *">
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="border-bottom rounded my-4">
                                        <textarea name="" id="" class="form-control border-0" cols="30" rows="8"
                                            placeholder="Your Review *" spellcheck="false"></textarea>
                                    </div>
                                </div>
                                <div class="col-lg-12">
                                    <div class="d-flex justify-content-between py-3 mb-5">
                                        <div class="d-flex align-items-center">
                                            <p class="mb-0 me-3">Please rate:</p>
                                            <div class="d-flex align-items-center" style="font-size: 12px;">
                                                <i class="fa fa-star text-muted"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                                <i class="fa fa-star"></i>
                                            </div>
                                        </div>
                                        <a href="#"
                                            class="btn btn-primary border border-secondary text-primary rounded-pill px-4 py-3">
                                            Post Comment</a>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="row g-4 mt-4">
                <div class="col-12">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 class="mb-0">Related Products</h3>
                        <small class="text-muted"><?= count($relatedProducts) ?> item<?= count($relatedProducts) === 1 ? '' : 's' ?></small>
                    </div>
                    <?php if (!empty($relatedProducts)): ?>
                        <div class="related-carousel owl-carousel">
                            <?php foreach ($relatedProducts as $relatedProduct): ?>
                                <?php
                                $relatedImage = !empty($relatedProduct['photo_path']) ? (string)$relatedProduct['photo_path'] : 'assets/img/product-3.png';
                                $relatedStock = (int)($relatedProduct['quantity'] ?? 0);
                                $relatedStockLabel = $relatedStock <= 0 ? 'Out of stock' : ($relatedStock <= 5 ? 'Low stock' : 'In stock');
                                $relatedStockClass = $relatedStock <= 0 ? 'bg-danger' : ($relatedStock <= 5 ? 'bg-warning text-dark' : 'bg-success');
                                ?>
                                <div class="border rounded p-3 bg-light h-100">
                                    <div class="position-relative mb-3">
                                        <img src="<?= e($relatedImage) ?>" class="img-fluid rounded w-100" style="height: 220px; object-fit: cover;" alt="<?= e($relatedProduct['designation']) ?>">
                                        <span class="badge <?= e($relatedStockClass) ?> rounded-pill position-absolute top-0 end-0 m-2" style="z-index: 2;">
                                            <?= e($relatedStockLabel) ?>
                                        </span>
                                    </div>
                                    <h5 class="mb-2"><?= e($relatedProduct['designation']) ?></h5>
                                    <p class="text-muted mb-2"><?= e($relatedProduct['brand']) ?></p>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <strong><?= number_format((float)$relatedProduct['price'], 2) ?> $</strong>
                                        <small class="text-muted"><?= $relatedStock ?> in stock</small>
                                    </div>
                                    <a href="single.php?id=<?= (int)$relatedProduct['id'] ?>" class="btn btn-primary rounded-pill w-100">View Product</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border mb-0">No related products found in this category yet.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <!-- Single Products End -->

    

    <!-- Footer Start -->
    <div class="container-fluid footer py-5 wow fadeIn" data-wow-delay="0.2s">
        <div class="container py-5">
            <div class="row g-4 rounded mb-5" style="background: rgba(255, 255, 255, .03);">
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="rounded p-4">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-4"
                            style="width: 70px; height: 70px;">
                            <i class="fas fa-map-marker-alt fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h4 class="text-white">Address</h4>
                            <p class="mb-2">sfax 5 Aout</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="rounded p-4">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-4"
                            style="width: 70px; height: 70px;">
                            <i class="fas fa-envelope fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h4 class="text-white">Mail Us</h4>
                            <p class="mb-2">ahmedaminkhaled21@gmail.com</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="rounded p-4">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-4"
                            style="width: 70px; height: 70px;">
                            <i class="fa fa-phone-alt fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h4 class="text-white">Telephone</h4>
                            <p class="mb-2">20677687</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="rounded p-4">
                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center mb-4"
                            style="width: 70px; height: 70px;">
                            <i class="fab fa-firefox-browser fa-2x text-primary"></i>
                        </div>
                        <div>
                            <h4 class="text-white">ahmedaminkhaled21@gmail.com</h4>
                            <p class="mb-2">20677687</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-5">
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="footer-item d-flex flex-column">
                        <div class="footer-item">
                            <h4 class="text-primary mb-4">Newsletter</h4>
                            <p class="text-white mb-3"></p>
                            <div class="position-relative mx-auto rounded-pill">
                                <input class="form-control rounded-pill w-100 py-3 ps-4 pe-5" type="text"
                                    placeholder="Enter your email">
                                <button type="button"
                                    class="btn btn-primary rounded-pill position-absolute top-0 end-0 py-2 mt-2 me-2">SignUp</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="footer-item d-flex flex-column">
                        <h4 class="text-primary mb-4">Customer Service</h4>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Contact Us</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Returns</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Order History</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Site Map</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Testimonials</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> My Account</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Unsubscribe Notification</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="footer-item d-flex flex-column">
                        <h4 class="text-primary mb-4">Information</h4>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> About Us</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Delivery information</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Privacy Policy</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Terms & Conditions</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Warranty</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> FAQ</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Seller Login</a>
                    </div>
                </div>
                <div class="col-md-6 col-lg-6 col-xl-3">
                    <div class="footer-item d-flex flex-column">
                        <h4 class="text-primary mb-4">Extras</h4>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Brands</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Gift Vouchers</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Affiliates</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Wishlist</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Order History</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Track Your Order</a>
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Track Your Order</a>
                    </div>
                </div>
            </div>
            <div class="text-center mt-4 pt-4 border-top border-light">
                <p class="mb-0 text-white-50">Worked on by Ahmed Amin Khaled and Rayen Belgith</p>
            </div>
        </div>
    </div>
    <!-- Footer End -->



    <!-- Back to Top -->
    <a href="#" class="btn btn-primary btn-lg-square back-to-top"><i class="fa fa-arrow-up"></i></a>


    <!-- JavaScript Libraries -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/lib/wow/wow.min.js"></script>
    <script src="assets/lib/easing/easing.min.js"></script>
    <script src="assets/lib/waypoints/waypoints.min.js"></script>
    <script src="assets/lib/counterup/counterup.min.js"></script>
    <script src="assets/lib/owlcarousel/owl.carousel.min.js"></script>
    <script src="assets/lib/lightbox/js/lightbox.min.js"></script>

    <script src="assets/js/main.js"></script>
</body>

</html>