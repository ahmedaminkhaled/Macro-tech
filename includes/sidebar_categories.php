<?php

declare(strict_types=1);

$categories = [];

try {
    $stmt = db()->query(
        'SELECT c.id, c.name, COUNT(p.id) AS products_count
         FROM categories c
         LEFT JOIN products p ON p.category_id = c.id
         GROUP BY c.id, c.name
         ORDER BY c.name ASC'
    );
    $categories = $stmt->fetchAll();
} catch (Throwable $e) {
    $categories = [];
}
?>
<ul class="list-unstyled categories-bars">
    <?php if (!empty($categories)): ?>
        <?php foreach ($categories as $category): ?>
            <li>
                <div class="categories-bars-item">
                    <a href="shop.php?category_id=<?= (int)$category['id'] ?>"><?= e($category['name']) ?></a>
                    <span>(<?= (int)$category['products_count'] ?>)</span>
                </div>
            </li>
        <?php endforeach; ?>
    <?php else: ?>
        <li>
            <div class="categories-bars-item">
                <a href="shop.php">All Products</a>
                <span>(0)</span>
            </div>
        </li>
    <?php endif; ?>
</ul>
