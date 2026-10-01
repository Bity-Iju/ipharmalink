<?php

/**
 * How it works — /how-it-works
 *
 * The B2B model explained: wholesale suppliers sell in bulk to retail
 * pharmacies, and those pharmacies then serve their own end customers.
 *
 * @var array $stats
 */
$stats = $stats ?? ['pharmacies' => 0, 'products' => 0, 'categories' => 0];

$steps = [
    [
        'icon'  => 'bi-building',
        'title' => 'Suppliers list in bulk',
        'body'  => 'Verified wholesalers, distributors and pharmaceutical suppliers publish their products with wholesale pricing, minimum order quantities and carton or case quantities.',
    ],
    [
        'icon'  => 'bi-shop-window',
        'title' => 'Retail pharmacies buy wholesale',
        'body'  => 'Pharmacies discover suppliers, compare prices and stock availability, then place bulk purchase orders paid by transfer, card or approved credit terms.',
    ],
    [
        'icon'  => 'bi-box-seam',
        'title' => 'Goods arrive and stock updates',
        'body'  => 'Deliveries are tracked from dispatch to receipt. On receipt, the pharmacy inventory increases with full batch and expiry tracking.',
    ],
    [
        'icon'  => 'bi-tags',
        'title' => 'Pharmacies set their own retail prices',
        'body'  => 'A pharmacy decides its own retail price and margin. Wholesale cost and profit stay private to the pharmacy.',
    ],
    [
        'icon'  => 'bi-people',
        'title' => 'End customers buy from the pharmacy',
        'body'  => 'Customers order from the pharmacy storefront, pay the pharmacy directly, and choose delivery or pickup.',
    ],
    [
        'icon'  => 'bi-graph-up-arrow',
        'title' => 'Everyone tracks their own numbers',
        'body'  => 'Suppliers see their sales, pharmacies see their margin, and administrators oversee the whole ecosystem.',
    ],
];
?>
<section class="bg-soft py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-7">
                <h1 class="h2 section-title mb-3">A wholesale marketplace built for pharmacies</h1>
                <p class="lead text-muted mb-0">
                    iPharmaLink connects pharmaceutical suppliers with the retail pharmacies that buy from them.
                    Bulk purchasing happens on one side; retail selling happens on the other. The two are tracked
                    completely separately, so every business only ever sees the numbers that belong to them.
                </p>
            </div>
            <div class="col-lg-5">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="ipl-card p-3 h-100">
                            <div class="h4 fw-bold mb-0"><?= number_format($stats['pharmacies']) ?></div>
                            <div class="small text-muted">Verified pharmacies</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="ipl-card p-3 h-100">
                            <div class="h4 fw-bold mb-0"><?= number_format($stats['products']) ?></div>
                            <div class="small text-muted">Products listed</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="ipl-card p-3 h-100">
                            <div class="h4 fw-bold mb-0"><?= number_format($stats['categories']) ?></div>
                            <div class="small text-muted">Categories</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-5">
                <h2 class="h3 section-title mb-3">The supply chain</h2>
                <p class="text-muted mb-4">
                    Three parties, two very different kinds of transaction. The wholesale trade happens between
                    businesses; the retail trade happens between a pharmacy and its customer.
                </p>

                <div class="ipl-card p-3 mb-3">
                    <div class="small text-uppercase text-muted fw-bold mb-2">Wholesale trade</div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="chip bg-brand text-white">Wholesale supplier</span>
                        <i class="bi bi-arrow-right"></i>
                        <span class="chip">Retail pharmacy</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Bought by the carton or case, priced at wholesale, tracked with its own orders, invoices,
                        payments and deliveries.
                    </p>
                </div>

                <div class="ipl-card p-3 mb-3">
                    <div class="small text-uppercase text-muted fw-bold mb-2">Retail trade</div>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <span class="chip bg-brand text-white">Retail pharmacy</span>
                        <i class="bi bi-arrow-right"></i>
                        <span class="chip">End customer</span>
                    </div>
                    <p class="small text-muted mb-0">
                        Priced by the pharmacy, fulfilled by the pharmacy, and never visible to the supplier.
                    </p>
                </div>
            </div>

            <div class="col-lg-7">
                <h2 class="h3 section-title mb-3">Step by step</h2>
                <div class="row g-3">
                    <?php foreach ($steps as $i => $step): ?>
                        <div class="col-md-6">
                            <div class="ipl-card p-3 h-100">
                                <div class="d-flex align-items-start gap-3">
                                    <span class="brand-mark flex-shrink-0">
                                        <i class="bi <?= e($step['icon']) ?>"></i>
                                    </span>
                                    <div>
                                        <div class="small text-muted fw-bold">Step <?= $i + 1 ?></div>
                                        <h3 class="h6 fw-bold mb-1"><?= e($step['title']) ?></h3>
                                        <p class="small text-muted mb-0"><?= e($step['body']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<section class="bg-soft py-5">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-lg-6">
                <h2 class="h3 section-title mb-3">Why it matters</h2>
                <ul class="list-unstyled d-grid gap-3 mb-0">
                    <?php
                    $benefits = [
                        'Better buying power through visible wholesale pricing and volume tiers.',
                        'Batch and expiry tracking, so you sell stock before it turns.',
                        'Your cost price and profit margin stay completely private.',
                        'Delivery tracking from the supplier all the way to your counter.',
                        'Compliance built in: prescription-only and restricted products are handled by rule.',
                    ];
                    foreach ($benefits as $benefit):
                    ?>
                        <li class="d-flex gap-2">
                            <i class="bi bi-check-circle-fill text-success flex-shrink-0"></i>
                            <span class="text-muted"><?= e($benefit) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="ipl-card p-4 text-center">
                    <h3 class="h5 fw-bold mb-3">Ready to take part?</h3>
                    <p class="text-muted small">
                        Register your pharmacy to buy wholesale, or apply to sell as a verified supplier.
                    </p>
                    <div class="d-grid gap-2">
                        <a href="/register" class="btn btn-primary">Register your pharmacy</a>
                        <a href="/pharmacies" class="btn btn-light">Browse pharmacies</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>