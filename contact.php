<?php
require_once __DIR__ . '/config/app.php';

$errors = [];
$isEdit = false;
$editCategory = [
    'id' => null,
    'name' => '',
    'description' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_valid(is_string($token) ? $token : null)) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'create' || $action === 'update') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $name = trim((string)($_POST['name'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));

            if ($name === '') {
                $errors[] = 'Category name is required.';
            }

            if (mb_strlen($name) > 100) {
                $errors[] = 'Category name must be at most 100 characters.';
            }

            if (mb_strlen($description) > 255) {
                $errors[] = 'Description must be at most 255 characters.';
            }

            if (empty($errors)) {
                $sqlCheck = 'SELECT id FROM categories WHERE LOWER(name) = LOWER(:name)';
                if ($action === 'update') {
                    $sqlCheck .= ' AND id <> :id';
                }
                $stmtCheck = db()->prepare($sqlCheck);
                $stmtCheck->bindValue(':name', $name);
                if ($action === 'update') {
                    $stmtCheck->bindValue(':id', $id, PDO::PARAM_INT);
                }
                $stmtCheck->execute();

                if ($stmtCheck->fetch()) {
                    $errors[] = 'Category already exists.';
                } else {
                    if ($action === 'create') {
                        $stmt = db()->prepare('INSERT INTO categories(name, description) VALUES (:name, :description)');
                        $stmt->bindValue(':name', $name);
                        $stmt->bindValue(':description', $description !== '' ? $description : null);
                        $stmt->execute();
                        set_flash('success', 'Category added successfully.');
                    } else {
                        $stmt = db()->prepare('UPDATE categories SET name = :name, description = :description WHERE id = :id');
                        $stmt->bindValue(':name', $name);
                        $stmt->bindValue(':description', $description !== '' ? $description : null);
                        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
                        $stmt->execute();
                        set_flash('success', 'Category updated successfully.');
                    }

                    redirect('contact.php');
                }
            }

            if ($action === 'update') {
                $isEdit = true;
                $editCategory = [
                    'id' => $id,
                    'name' => $name,
                    'description' => $description,
                ];
            }
        }

        if ($action === 'delete') {
            $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

            $stmtLinked = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = :id');
            $stmtLinked->bindValue(':id', $id, PDO::PARAM_INT);
            $stmtLinked->execute();
            $linkedProducts = (int)$stmtLinked->fetchColumn();

            if ($linkedProducts > 0) {
                set_flash('error', 'Cannot delete category: linked products exist.');
            } else {
                $stmtDelete = db()->prepare('DELETE FROM categories WHERE id = :id');
                $stmtDelete->bindValue(':id', $id, PDO::PARAM_INT);
                $stmtDelete->execute();
                set_flash('success', 'Category deleted successfully.');
            }

            redirect('contact.php');
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    if ($editId > 0) {
        $stmtEdit = db()->prepare('SELECT id, name, description FROM categories WHERE id = :id');
        $stmtEdit->bindValue(':id', $editId, PDO::PARAM_INT);
        $stmtEdit->execute();
        $row = $stmtEdit->fetch();

        if ($row) {
            $isEdit = true;
            $editCategory = $row;
        }
    }
}

$stmtCategories = db()->query(
    'SELECT c.id, c.name, c.description, COUNT(p.id) AS products_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.description
     ORDER BY c.name ASC'
);
$categories = $stmtCategories->fetchAll();

$successMessage = flash('success');
$errorMessage = flash('error');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>MacroTech - Electronics Website Template</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="" name="keywords">
    <meta content="" name="description">

    <script>
        (function () {
            try {
                var theme = localStorage.getItem('theme') || 'default';
                var vars = {
                    ocean: {
                        '--bs-primary': '#0d6efd',
                        '--bs-primary-rgb': '13, 110, 253',
                        '--bs-secondary': '#20c997',
                        '--bs-secondary-rgb': '32, 201, 151',
                        '--bs-dark': '#0b132b',
                        '--bs-dark-rgb': '11, 19, 43',
                        '--bs-light': '#e7f1ff',
                        '--bs-light-rgb': '231, 241, 255'
                    },
                    sunset: {
                        '--bs-primary': '#ff6b6b',
                        '--bs-primary-rgb': '255, 107, 107',
                        '--bs-secondary': '#f7b267',
                        '--bs-secondary-rgb': '247, 178, 103',
                        '--bs-dark': '#3d2c2e',
                        '--bs-dark-rgb': '61, 44, 46',
                        '--bs-light': '#fff3e6',
                        '--bs-light-rgb': '255, 243, 230'
                    },
                    forest: {
                        '--bs-primary': '#2d6a4f',
                        '--bs-primary-rgb': '45, 106, 79',
                        '--bs-secondary': '#52b788',
                        '--bs-secondary-rgb': '82, 183, 136',
                        '--bs-dark': '#1b4332',
                        '--bs-dark-rgb': '27, 67, 50',
                        '--bs-light': '#e9f5ee',
                        '--bs-light-rgb': '233, 245, 238'
                    }
                };
                if (theme !== 'default' && vars[theme]) {
                    document.documentElement.setAttribute('data-theme', theme);
                    Object.keys(vars[theme]).forEach(function (key) {
                        document.documentElement.style.setProperty(key, vars[theme][key]);
                    });
                }
            } catch (e) {}
        })();
    </script>

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
                    <div class="theme-switcher btn-group btn-group-sm ms-3" role="group" aria-label="Theme switcher">
                        <button type="button" class="btn btn-outline-light theme-btn" data-theme="default">Default</button>
                        <button type="button" class="btn btn-outline-light theme-btn" data-theme="ocean">Ocean</button>
                        <button type="button" class="btn btn-outline-light theme-btn" data-theme="sunset">Sunset</button>
                        <button type="button" class="btn btn-outline-light theme-btn" data-theme="forest">Forest</button>
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
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['id'] ?>"><?= e($category['name']) ?></option>
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
                            <a href="index.php" class="nav-item nav-link">Home</a>
                            <a href="shop.php" class="nav-item nav-link">Shop</a>
                            <a href="single.php" class="nav-item nav-link">Single Page</a>
                            <a href="contact.php" class="nav-item nav-link me-2 active">Contact</a>
                            <div class="nav-item dropdown d-block d-lg-none mb-3">
                                <a href="#" class="nav-link" data-bs-toggle="dropdown"><span class="dropdown-toggle">All
                                        Category</span></a>
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
        <h1 class="text-center text-white display-6 wow fadeInUp" data-wow-delay="0.1s">Category Management</h1>
        <ol class="breadcrumb justify-content-center mb-0 wow fadeInUp" data-wow-delay="0.3s">
            <li class="breadcrumb-item"><a href="#">Home</a></li>
            <li class="breadcrumb-item"><a href="#">Pages</a></li>
            <li class="breadcrumb-item active text-white">Categories</li>
        </ol>
    </div>
    <!-- Single Page Header End -->

    <!-- Contucts Start -->
    <div class="container-fluid contact py-5">
        <div class="container py-5">
            <div class="p-5 bg-light rounded">
                <div class="row g-4">
                    <div class="col-12">
                        <div class="text-center mx-auto wow fadeInUp" data-wow-delay="0.1s" style="max-width: 900px;">
                            <h4 class="text-primary border-bottom border-primary border-2 d-inline-block pb-2">Category Management</h4>
                            <p class="mb-4 fs-5 text-dark">Add, update, and delete product categories.</p>
                        </div>
                    </div>

                    <div class="col-12">
                        <?php if ($successMessage): ?>
                            <div class="alert alert-success"><?= e($successMessage) ?></div>
                        <?php endif; ?>
                        <?php if ($errorMessage): ?>
                            <div class="alert alert-danger"><?= e($errorMessage) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?= e($error) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-lg-5">
                        <h5 class="text-primary wow fadeInUp" data-wow-delay="0.1s">Category Form</h5>
                        <h1 class="display-6 mb-4 wow fadeInUp" data-wow-delay="0.3s">
                            <?= $isEdit ? 'Update Category' : 'Add Category' ?>
                        </h1>

                        <form method="post" action="contact.php" class="wow fadeInUp" data-wow-delay="0.1s">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                            <?php if ($isEdit): ?>
                                <input type="hidden" name="id" value="<?= (int)$editCategory['id'] ?>">
                            <?php endif; ?>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="category_name" name="name"
                                       maxlength="100" required
                                       value="<?= e((string)$editCategory['name']) ?>"
                                       placeholder="Category Name">
                                <label for="category_name">Category Name</label>
                            </div>

                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="category_description" name="description"
                                       maxlength="255"
                                       value="<?= e((string)$editCategory['description']) ?>"
                                       placeholder="Description">
                                <label for="category_description">Description (optional)</label>
                            </div>

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary py-3 px-4" type="submit">
                                    <?= $isEdit ? 'Update Category' : 'Add Category' ?>
                                </button>
                                <?php if ($isEdit): ?>
                                    <a href="contact.php" class="btn btn-secondary py-3 px-4">Cancel</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="col-lg-7 wow fadeInUp" data-wow-delay="0.2s">
                        <h5 class="text-primary">Category List</h5>
                        <div class="table-responsive bg-white rounded p-3 border">
                            <table class="table table-bordered table-hover align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Products</th>
                                    <th style="min-width: 170px;">Actions</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center">No categories found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $category): ?>
                                        <tr>
                                            <td><?= (int)$category['id'] ?></td>
                                            <td><?= e($category['name']) ?></td>
                                            <td><?= e((string)$category['description']) ?></td>
                                            <td><?= (int)$category['products_count'] ?></td>
                                            <td>
                                                <a class="btn btn-sm btn-outline-primary"
                                                   href="contact.php?edit=<?= (int)$category['id'] ?>">Edit</a>

                                                <form method="post" action="contact.php" class="d-inline"
                                                      onsubmit="return confirm('Delete this category?');">
                                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
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
    </div>
    <!-- Contuct End -->

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