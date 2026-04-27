<?php
require_once __DIR__ . '/config/app.php';

$stockErrors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'create_sale')) {
    $token = $_POST['csrf_token'] ?? '';
    if (!csrf_valid(is_string($token) ? $token : null)) {
        $stockErrors[] = 'Invalid CSRF token.';
    } else {
        $productId = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
        $quantity   = isset($_POST['quantity'])   ? (int)$_POST['quantity']   : 0;

        if ($productId <= 0) $stockErrors[] = 'Product is required.';
        if ($quantity   <= 0) $stockErrors[] = 'Sale quantity must be greater than 0.';

        if (empty($stockErrors)) {
            try {
                db()->beginTransaction();

                $stmtProduct = db()->prepare('SELECT id, price, quantity FROM products WHERE id = :id FOR UPDATE');
                $stmtProduct->bindValue(':id', $productId, PDO::PARAM_INT);
                $stmtProduct->execute();
                $product = $stmtProduct->fetch();

                if (!$product) {
                    throw new RuntimeException('Product not found.');
                }
                if ((int)$product['quantity'] < $quantity) {
                    throw new RuntimeException('Insufficient stock. Available: ' . (int)$product['quantity']);
                }

                $unitPrice  = (float)$product['price'];
                $lineTotal  = $unitPrice * $quantity;

                // Insert sale with correct total
                $stmtSale = db()->prepare(
                    'INSERT INTO sales(sale_date, total_amount, created_by) VALUES (NOW(), :total, :created_by)'
                );
                $stmtSale->bindValue(':total', $lineTotal);
                $stmtSale->bindValue(':created_by', 'local-admin');
                $stmtSale->execute();
                $saleId = (int)db()->lastInsertId();

                // Insert sale item
                $stmtItem = db()->prepare(
                    'INSERT INTO sale_items(sale_id, product_id, quantity, unit_price, line_total)
                     VALUES (:sale_id, :product_id, :quantity, :unit_price, :line_total)'
                );
                $stmtItem->bindValue(':sale_id',    $saleId,    PDO::PARAM_INT);
                $stmtItem->bindValue(':product_id', $productId, PDO::PARAM_INT);
                $stmtItem->bindValue(':quantity',   $quantity,  PDO::PARAM_INT);
                $stmtItem->bindValue(':unit_price', $unitPrice);
                $stmtItem->bindValue(':line_total', $lineTotal);
                $stmtItem->execute();

                // Deduct stock — this was missing before
                $stmtStock = db()->prepare(
                    'UPDATE products SET quantity = quantity - :qty WHERE id = :id'
                );
                $stmtStock->bindValue(':qty', $quantity, PDO::PARAM_INT);
                $stmtStock->bindValue(':id',  $productId, PDO::PARAM_INT);
                $stmtStock->execute();

                db()->commit();
                set_flash('success', 'Sale recorded. Stock updated.');
                redirect('cheackout.php');

            } catch (Throwable $e) {
                if (db()->inTransaction()) db()->rollBack();
                $stockErrors[] = $e->getMessage();
            }
        }
    }
}

$productsInStock = db()->query(
    'SELECT id, reference, designation, brand, price, quantity
     FROM products WHERE quantity > 0 ORDER BY designation ASC'
)->fetchAll();

$recentSales = db()->query(
    'SELECT s.id, s.sale_date, s.total_amount, p.designation, si.quantity, si.unit_price
     FROM sales s
     INNER JOIN sale_items si ON si.sale_id = s.id
     INNER JOIN products p ON p.id = si.product_id
     ORDER BY s.id DESC LIMIT 10'
)->fetchAll();

$searchCategories = [];
try {
    $stmtCategories = db()->query('SELECT id, name FROM categories ORDER BY name ASC');
    $searchCategories = $stmtCategories->fetchAll();
} catch (Throwable $e) {
    $searchCategories = [];
}

$stockSuccessMessage = flash('success');
$stockErrorMessage   = flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Checkout – Electro</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/lib/animate/animate.min.css" rel="stylesheet">
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Spinner -->
<div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
    <div class="spinner-border text-primary" style="width:3rem;height:3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
</div>
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
                            <a href="#" class="dropdown-item"> Dolar</a>
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

<!-- Navbar -->
<div class="container-fluid nav-bar p-0">
    <div class="row gx-0 bg-primary px-5 align-items-center">
        <div class="col-lg-3 d-none d-lg-block">
            <nav class="navbar navbar-light position-relative" style="width:250px;">
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
            <nav class="navbar navbar-expand-lg navbar-light bg-primary">
                <a href="" class="navbar-brand d-block d-lg-none">
                    <h1 class="display-5 text-secondary m-0"><i class="fas fa-shopping-bag text-white me-2"></i>Electro</h1>
                </a>
                <button class="navbar-toggler ms-auto" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars fa-1x"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarCollapse">
                    <div class="navbar-nav ms-auto py-0">
                        <a href="index.php"     class="nav-item nav-link">Home</a>
                        <a href="shop.php"      class="nav-item nav-link">Shop</a>
                        <a href="single.php"    class="nav-item nav-link">Single Page</a>
                        
                        <a href="contact.php" class="nav-item nav-link me-2">Contact</a>
                    </div>
                    <a href="" class="btn btn-secondary rounded-pill py-2 px-4 px-lg-3 mb-3 mb-md-3 mb-lg-0">
                        <i class="fa fa-mobile-alt me-2"></i> +0123 456 7890
                    </a>
                </div>
            </nav>
        </div>
    </div>
</div>

<!-- Page Header -->
<div class="container-fluid page-header py-5">
    <h1 class="text-center text-white display-6 wow fadeInUp" data-wow-delay="0.1s">Checkout</h1>
    <ol class="breadcrumb justify-content-center mb-0 wow fadeInUp" data-wow-delay="0.3s">
        <li class="breadcrumb-item"><a href="index.php">Home</a></li>
        <li class="breadcrumb-item active text-white">Checkout</li>
    </ol>
</div>

<!-- Sale Form + Recent Sales -->
<div class="container-fluid py-5">
    <div class="container py-5">

        <?php if ($stockSuccessMessage): ?>
            <div class="alert alert-success"><?= e($stockSuccessMessage) ?></div>
        <?php endif; ?>
        <?php if ($stockErrorMessage): ?>
            <div class="alert alert-danger"><?= e($stockErrorMessage) ?></div>
        <?php endif; ?>
        <?php if (!empty($stockErrors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($stockErrors as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row g-4">

            <!-- Sale Form -->
            <div class="col-lg-4 wow fadeInLeft" data-wow-delay="0.1s">
                <div class="p-4 border rounded bg-light h-100">
                    <h5 class="text-primary mb-1">Record a Sale</h5>
                    <p class="text-muted small mb-3">Selecting a product and quantity will deduct from stock.</p>

                    <form method="post" action="cheackout.php">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action"     value="create_sale">

                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">Select product</option>
                                <?php foreach ($productsInStock as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>">
                                        <?= e($p['designation']) ?>
                                        (<?= e($p['reference']) ?>)
                                        — Stock: <?= (int)$p['quantity'] ?>
                                        — $<?= number_format((float)$p['price'], 2) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Quantity Sold</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Confirm Sale</button>
                    </form>
                </div>
            </div>

            <!-- Recent Sales Table -->
            <div class="col-lg-8 wow fadeInRight" data-wow-delay="0.2s">
                <div class="table-responsive bg-white rounded p-3 border">
                    <h5 class="text-primary mb-3">Recent Sales</h5>
                    <table class="table table-bordered table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Sale ID</th>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Qty</th>
                                <th>Unit Price</th>
                                <th>Line Total</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($recentSales)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">No sales yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recentSales as $sale): ?>
                                <tr>
                                    <td><?= (int)$sale['id'] ?></td>
                                    <td><?= e($sale['sale_date']) ?></td>
                                    <td><?= e($sale['designation']) ?></td>
                                    <td><?= (int)$sale['quantity'] ?></td>
                                    <td>$<?= number_format((float)$sale['unit_price'], 2) ?></td>
                                    <td>$<?= number_format((float)($sale['unit_price'] * $sale['quantity']), 2) ?></td>
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
                        <a href="#" class=""><i class="fas fa-angle-right me-2"></i> Delivery infomation</a>
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
        </div>
    </div>
    <!-- Footer End -->

<a href="#" class="btn btn-primary btn-lg-square back-to-top"><i class="fa fa-arrow-up"></i></a>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/lib/wow/wow.min.js"></script>
<script src="assets/lib/owlcarousel/owl.carousel.min.js"></script>
<script src="assets/js/main.js"></script>
</body>
</html>