<?php
require_once __DIR__ . '/config/app.php';

$productErrors = [];
$isEditProduct = false;
$editProduct = [
    'id' => null,
    'reference' => '',
    'designation' => '',
    'description' => '',
    'brand' => '',
    'price' => '',
    'quantity' => '0',
    'photo_path' => '',
    'category_id' => '',
];

$categoriesStmt = db()->query('SELECT id, name FROM categories ORDER BY name ASC');
$categoryOptions = $categoriesStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_valid(is_string($token) ? $token : null)) {
        $productErrors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $reference = trim((string)($_POST['reference'] ?? ''));
            $designation = trim((string)($_POST['designation'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $brand = trim((string)($_POST['brand'] ?? ''));
            $price = (string)($_POST['price'] ?? '');
            $quantity = (string)($_POST['quantity'] ?? '0');
            $categoryId = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
            $photoPath = trim((string)($_POST['photo'] ?? ''));
            if ($action === 'create' && $reference === '') {
                $productErrors[] = 'Reference is required.';
            }
            if ($designation === '') {
                $productErrors[] = 'Designation is required.';
            }
            if ($brand === '') {
                $productErrors[] = 'Brand is required.';
            }
            if (!is_numeric($price) || (float)$price <= 0) {
                $productErrors[] = 'Price must be strictly positive.';
            }
            if (!ctype_digit((string)$quantity)) {
                $productErrors[] = 'Quantity must be an integer >= 0.';
            }
            if ($categoryId <= 0) {
                $productErrors[] = 'Category is required.';
            }

            
            if ($action === 'update') {
                $stmtCurrent = db()->prepare('SELECT photo_path, reference FROM products WHERE id = :id');
                $stmtCurrent->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtCurrent->execute();
                $current = $stmtCurrent->fetch();

                if (!$current) {
                    $productErrors[] = 'Product not found.';
                } else {
                    $photoPath = (string)$current['photo_path'];
                    $reference = (string)$current['reference'];
                }
            }

            if (isset($_FILES['photo']) && (int)$_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
                if ((int)$_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
                    $productErrors[] = 'Photo upload failed.';
                } else {
                    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
                    $fileName = (string)$_FILES['photo']['name'];
                    $tmpName = (string)$_FILES['photo']['tmp_name'];
                    $size = (int)$_FILES['photo']['size'];
                    $ext = strtolower((string)pathinfo($fileName, PATHINFO_EXTENSION));

                    if (!in_array($ext, $allowedExt, true)) {
                        $productErrors[] = 'Photo extension must be jpg, jpeg, png, or webp.';
                    }

                    if ($size > 2 * 1024 * 1024) {
                        $productErrors[] = 'Photo size must not exceed 2MB.';
                    }

                    $mime = '';
                    if (function_exists('finfo_open')) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        if ($finfo) {
                            $mime = (string)finfo_file($finfo, $tmpName);
                            finfo_close($finfo);
                        }
                    }
                    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
                    if ($mime !== '' && !in_array($mime, $allowedMime, true)) {
                        $productErrors[] = 'Invalid photo MIME type.';
                    }

                    if (empty($productErrors)) {
                        $newFileName = bin2hex(random_bytes(16)) . '.' . $ext;
                        $targetDir = __DIR__ . '/uploads/products/';
                        $targetPath = $targetDir . $newFileName;

                                        if (!is_dir($targetDir)) {
                    if (!mkdir($targetDir, 0775, true)) {
                        $productErrors[] = 'Unable to create upload directory.';
                    }
                }

                if (empty($productErrors)) {
                    if (!move_uploaded_file($tmpName, $targetPath)) {
                        $productErrors[] = 'Unable to save uploaded photo.';
                    } else {
                        $photoPath = 'uploads/products/' . $newFileName;
                    }
                }
                    }
                }
            }

            if ($action === 'create' && $reference !== '') {
                $stmtRef = db()->prepare('SELECT id FROM products WHERE reference = :reference');
                $stmtRef->bindValue(':reference', $reference);
                $stmtRef->execute();
                if ($stmtRef->fetch()) {
                    $productErrors[] = 'Reference already exists.';
                }
            }

            if (empty($productErrors)) {
                if ($action === 'create') {
                    $stmtInsert = db()->prepare(
                        'INSERT INTO products(reference, designation, description, brand, price, quantity, photo_path, category_id)
                         VALUES (:reference, :designation, :description, :brand, :price, :quantity, :photo_path, :category_id)'
                    );
                    $stmtInsert->bindValue(':reference', $reference);
                    $stmtInsert->bindValue(':designation', $designation);
                    $stmtInsert->bindValue(':description', $description !== '' ? $description : null);
                    $stmtInsert->bindValue(':brand', $brand);
                    $stmtInsert->bindValue(':price', (float)$price);
                    $stmtInsert->bindValue(':quantity', (int)$quantity, PDO::PARAM_INT);
                    $stmtInsert->bindValue(':photo_path', $photoPath !== '' ? $photoPath : null);
                    $stmtInsert->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                    $stmtInsert->execute();
                    set_flash('success', 'Product added successfully.');
                } else {
                    $stmtUpdate = db()->prepare(
                        'UPDATE products
                         SET designation = :designation,
                             description = :description,
                             brand = :brand,
                             price = :price,
                             quantity = :quantity,
                             photo_path = :photo_path,
                             category_id = :category_id
                         WHERE id = :id'
                    );
                    $stmtUpdate->bindValue(':designation', $designation);
                    $stmtUpdate->bindValue(':description', $description !== '' ? $description : null);
                    $stmtUpdate->bindValue(':brand', $brand);
                    $stmtUpdate->bindValue(':price', (float)$price);
                    $stmtUpdate->bindValue(':quantity', (int)$quantity, PDO::PARAM_INT);
                    $stmtUpdate->bindValue(':photo_path', $photoPath !== '' ? $photoPath : null);
                    $stmtUpdate->bindValue(':category_id', $categoryId, PDO::PARAM_INT);
                    $stmtUpdate->bindValue(':id', $id, PDO::PARAM_INT);
                    $stmtUpdate->execute();
                    set_flash('success', 'Product updated successfully.');
                }

                redirect('shop.php');
            }

            $isEditProduct = ($action === 'update');
            $editProduct = [
                'id' => $id,
                'reference' => $reference,
                'designation' => $designation,
                'description' => $description,
                'brand' => $brand,
                'price' => $price,
                'quantity' => $quantity,
                'photo_path' => $photoPath,
                'category_id' => (string)$categoryId,
            ];
        }

        if ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            $stmtQty = db()->prepare('SELECT quantity FROM products WHERE id = :id');
            $stmtQty->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtQty->execute();
            $qty = $stmtQty->fetchColumn();

            if ($qty === false) {
                set_flash('error', 'Product not found.');
            } elseif ((int)$qty !== 0) {
                set_flash('error', 'Product can be deleted only when quantity = 0.');
            } else {
                $stmtDelete = db()->prepare('DELETE FROM products WHERE id = :id');
                $stmtDelete->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtDelete->execute();
                set_flash('success', 'Product deleted successfully.');
            }

            redirect('shop.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmtEdit = db()->prepare(
            'SELECT id, reference, designation, description, brand, price, quantity, photo_path, category_id
             FROM products
             WHERE id = :id'
        );
        $stmtEdit->bindValue(':id', $editId, PDO::PARAM_INT);
        $stmtEdit->execute();
        $row = $stmtEdit->fetch();

        if ($row) {
            $isEditProduct = true;
            $editProduct = $row;
        }
    }
}

$selectedCategory = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$searchTerm = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? '');

$orderBy = 'p.id DESC';
if ($sort === 'price_asc') {
    $orderBy = 'p.price ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = 'p.price DESC';
} elseif ($sort === 'brand_asc') {
    $orderBy = 'p.brand ASC';
} elseif ($sort === 'brand_desc') {
    $orderBy = 'p.brand DESC';
}

$sql = 'SELECT p.id, p.reference, p.designation, p.description, p.brand, p.price, p.quantity, p.photo_path,
               c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE 1=1';

$params = [];
if ($selectedCategory > 0) {
    $sql .= ' AND p.category_id = :category_id';
    $params[':category_id'] = $selectedCategory;
}
if ($searchTerm !== '') {
    $sql .= ' AND p.designation LIKE :search';
    $params[':search'] = '%' . $searchTerm . '%';
}

$sql .= ' ORDER BY ' . $orderBy;
$productsStmt = db()->prepare($sql);
foreach ($params as $key => $value) {
    if ($key === ':category_id') {
        $productsStmt->bindValue($key, (int)$value, PDO::PARAM_INT);
    } else {
        $productsStmt->bindValue($key, $value);
    }
}
$productsStmt->execute();
$products = $productsStmt->fetchAll();

$productSuccessMessage = flash('success');
$productErrorMessage = flash('error');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>MacroTech - Electronics Website Template</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

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
                            value="<?= e($searchTerm) ?>" placeholder="Search Looking For?">
                        <select class="form-select text-dark border-0 border-start rounded-0 p-3" name="category_id" style="width: 200px;">
                            <option value="0">All Category</option>
                            <?php foreach ($categoryOptions as $category): ?>
                                <option value="<?= (int)$category['id'] ?>" <?= $selectedCategory === (int)$category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
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
        <h1 class="text-center text-white display-6 wow fadeInUp" data-wow-delay="0.1s">Shop Page</h1>
        <ol class="breadcrumb justify-content-center mb-0 wow fadeInUp" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li class="breadcrumb-item active text-white">Shop</li>
        </ol>
    </div>
    <!-- Single Page Header End -->

    <div class="container-fluid product py-5">
        <div class="container py-5">
            <?php if ($productSuccessMessage): ?>
                <div class="alert alert-success"><?= e($productSuccessMessage) ?></div>
            <?php endif; ?>
            <?php if ($productErrorMessage): ?>
                <div class="alert alert-danger"><?= e($productErrorMessage) ?></div>
            <?php endif; ?>
            <?php if (!empty($productErrors)): ?>
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        <?php foreach ($productErrors as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4 wow fadeInLeft" data-wow-delay="0.1s">
                    <div class="p-4 border rounded bg-light h-100">
                        <h5 class="text-primary"><?= $isEditProduct ? 'Update Product' : 'Add Product' ?></h5>
                        <form method="post" action="shop.php" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="<?= $isEditProduct ? 'update' : 'create' ?>">
                            <?php if ($isEditProduct): ?>
                                <input type="hidden" name="id" value="<?= (int)$editProduct['id'] ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Reference</label>
                                <input type="text" name="reference" class="form-control"
                                       value="<?= e((string)$editProduct['reference']) ?>"
                                       <?= $isEditProduct ? 'readonly' : 'required' ?>>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Designation</label>
                                <input type="text" name="designation" class="form-control" required
                                       value="<?= e((string)$editProduct['designation']) ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="3"><?= e((string)$editProduct['description']) ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Brand</label>
                                <input type="text" name="brand" class="form-control" required
                                       value="<?= e((string)$editProduct['brand']) ?>">
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label">Price</label>
                                    <input type="number" name="price" class="form-control" step="0.01" min="0.01" required
                                           value="<?= e((string)$editProduct['price']) ?>">
                                </div>
                                <div class="col-6">
                                    <label class="form-label">Quantity</label>
                                    <input type="number" name="quantity" class="form-control" min="0" required
                                           value="<?= e((string)$editProduct['quantity']) ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-select" required>
                                    <option value="">Select category</option>
                                    <?php foreach ($categoryOptions as $cat): ?>
                                        <option value="<?= (int)$cat['id'] ?>"
                                            <?= (string)$editProduct['category_id'] === (string)$cat['id'] ? 'selected' : '' ?>>
                                            <?= e($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Photo (jpg, jpeg, png, webp)</label>
                                <input type="file" name="photo" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                            </div>

                            <button type="submit" class="btn btn-primary w-100 mb-2">
                                <?= $isEditProduct ? 'Update Product' : 'Add Product' ?>
                            </button>
                            <?php if ($isEditProduct): ?>
                                <a href="shop.php" class="btn btn-secondary w-100">Cancel</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <div class="col-lg-8 wow fadeInRight" data-wow-delay="0.2s">
                    <form method="get" action="shop.php" class="row g-2 mb-3">
                        <div class="col-md-4">
                            <select name="category_id" class="form-select">
                                <option value="0">All Categories</option>
                                <?php foreach ($categoryOptions as $cat): ?>
                                    <option value="<?= (int)$cat['id'] ?>" <?= $selectedCategory === (int)$cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="text" name="q" class="form-control" placeholder="Search by designation"
                                   value="<?= e($searchTerm) ?>">
                        </div>
                        <div class="col-md-3">
                            <select name="sort" class="form-select">
                                <option value="">Default Sort</option>
                                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="brand_asc" <?= $sort === 'brand_asc' ? 'selected' : '' ?>>Brand: A-Z</option>
                                <option value="brand_desc" <?= $sort === 'brand_desc' ? 'selected' : '' ?>>Brand: Z-A</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-grid">
                            <button type="submit" class="btn btn-primary">Go</button>
                        </div>
                    </form>

                    <div class="table-responsive bg-white rounded p-3 border">
                        <table class="table table-bordered table-hover align-middle mb-0">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Reference</th>
                                <th>Designation</th>
                                <th>Brand</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Category</th>
                                <th>Photo</th>
                                <th style="min-width: 170px;">Actions</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No products found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?= (int)$product['id'] ?></td>
                                        <td><?= e($product['reference']) ?></td>
                                        <td><?= e($product['designation']) ?></td>
                                        <td><?= e($product['brand']) ?></td>
                                        <td><?= number_format((float)$product['price'], 2) ?></td>
                                        <td><?= (int)$product['quantity'] ?></td>
                                        <td><?= e($product['category_name']) ?></td>
                                        <td>
                                            <?php if (!empty($product['photo_path'])): ?>
                                                <img src="<?= e($product['photo_path']) ?>" alt="photo" style="width: 55px; height: 55px; object-fit: cover;" class="rounded">
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a class="btn btn-sm btn-outline-secondary" href="single.php?id=<?= (int)$product['id'] ?>">View</a>
                                            <a class="btn btn-sm btn-outline-primary" href="shop.php?edit=<?= (int)$product['id'] ?>">Edit</a>
                                            <form method="post" action="shop.php" class="d-inline" onsubmit="return confirm('Delete this product? Quantity must be 0.');">
                                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Searvices Start -->
    <div class="container-fluid px-0">
        <div class="row g-0">
            <div class="col-6 col-md-4 col-lg-2 border-start border-end wow fadeInUp" data-wow-delay="0.1s">
                <div class="p-4">
                    <div class="d-inline-flex align-items-center">
                        <i class="fa fa-sync-alt fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Free Return</h6>
                            <p class="mb-0">30 days money back guarantee!</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 border-end wow fadeInUp" data-wow-delay="0.2s">
                <div class="p-4">
                    <div class="d-flex align-items-center">
                        <i class="fab fa-telegram-plane fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Free Shipping</h6>
                            <p class="mb-0">Free shipping on all order</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 border-end wow fadeInUp" data-wow-delay="0.3s">
                <div class="p-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-life-ring fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Support 24/7</h6>
                            <p class="mb-0">We support online 24 hrs a day</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 border-end wow fadeInUp" data-wow-delay="0.4s">
                <div class="p-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-credit-card fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Receive Gift Card</h6>
                            <p class="mb-0">Receive gift on orders over $50</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 border-end wow fadeInUp" data-wow-delay="0.5s">
                <div class="p-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-lock fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Secure Payment</h6>
                            <p class="mb-0">We Value Your Security</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-lg-2 border-end wow fadeInUp" data-wow-delay="0.6s">
                <div class="p-4">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-blog fa-2x text-primary"></i>
                        <div class="ms-4">
                            <h6 class="text-uppercase mb-2">Online Service</h6>
                            <p class="mb-0">Free return products in 30 days</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Searvices End -->


    <!-- Products Offer Start -->
    <div class="container-fluid bg-light py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-6 wow fadeInLeft" data-wow-delay="0.2s">
                    <a href="#" class="d-flex align-items-center justify-content-between border bg-white rounded p-4">
                        <div>
                            <p class="text-muted mb-3">Find The Best Camera for You!</p>
                            <h3 class="text-primary">Smart Camera</h3>
                            <h1 class="display-3 text-secondary mb-0">40% <span
                                    class="text-primary fw-normal">Off</span></h1>
                        </div>
                        <img src="assets/img/product-1.png" class="img-fluid" alt="">
                    </a>
                </div>
                <div class="col-lg-6 wow fadeInRight" data-wow-delay="0.3s">
                    <a href="#" class="d-flex align-items-center justify-content-between border bg-white rounded p-4">
                        <div>
                            <p class="text-muted mb-3">Find The Best Whatches for You!</p>
                            <h3 class="text-primary">Smart Whatch</h3>
                            <h1 class="display-3 text-secondary mb-0">20% <span
                                    class="text-primary fw-normal">Off</span></h1>
                        </div>
                        <img src="assets/img/product-2.png" class="img-fluid" alt="">
                    </a>
                </div>
            </div>
        </div>
    </div>
    <!-- Products Offer End -->


    

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
    <script src="assets/lib/owlcarousel/owl.carousel.min.js"></script>

    <script src="assets/js/main.js"></script>
</body>

</html>