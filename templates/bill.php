<?php
/**
 * Billing & Payments Template
 * Integrates with WooCommerce and WooCommerce Subscriptions
 */

// Check if WooCommerce is active
if (!class_exists('WooCommerce')) {
    echo '<p>WooCommerce is required for this feature.</p>';
    return;
}

$user_id = get_current_user_id();
$customer = new WC_Customer($user_id);

// Get user's subscriptions
$subscriptions = [];
if (function_exists('wcs_get_users_subscriptions')) {
    $subscriptions = wcs_get_users_subscriptions($user_id);
}

// Get all orders for the user
$customer_orders = wc_get_orders([
    'customer' => $user_id,
    'limit' => -1,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'objects',
]);

// Calculate monthly spending and category breakdown
$current_month_total = 0;
$category_spending = [];
$total_all_time_spending = 0;

foreach ($customer_orders as $order) {
    if ($order->get_status() === 'completed' || $order->get_status() === 'processing') {
        $order_total = $order->get_total();
        $total_all_time_spending += $order_total;
        
        // Check if order is from current month
        $order_date = $order->get_date_created();
        if ($order_date->format('Y-m') === date('Y-m')) {
            $current_month_total += $order_total;
        }
        
        // Get category breakdown
        foreach ($order->get_items() as $item) {
            $product = $item->get_product();
            if ($product) {
                $categories = wp_get_post_terms($product->get_id(), 'product_cat');
                foreach ($categories as $category) {
                    if (!isset($category_spending[$category->name])) {
                        $category_spending[$category->name] = 0;
                    }
                    $category_spending[$category->name] += $item->get_total();
                }
            }
        }
    }
}

// Sort categories by spending
arsort($category_spending);
?>

<div class="billing-container common-padding">
    <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 2rem;">
        <div class="tab-head-button">
            <h2>Billing & Payments</h2>
            <span>Manage subscriptions and view payment history</span>
        </div>
        <div class="billing-actions">
            <button id="update-payment-card" class="btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect width="20" height="14" x="2" y="5" rx="2"></rect>
                    <line x1="2" x2="22" y1="10" y2="10"></line>
                </svg>
                Update Payment Card
            </button>
        </div>
    </div>

    <!-- Current Retainer Plans Section -->
    <div class="retainer-plans-section">
        <h3 class="section-title" style="font-size: 1.6rem;">
            <svg xmlns="http://www.w3.org/2000/svg" style="color:#44da67;" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-credit-card w-5 h-5 text-primary" data-lov-id="src/components/portal/BillingPayments.tsx:42:14" data-lov-name="CreditCard" data-component-path="src/components/portal/BillingPayments.tsx" data-component-line="42" data-component-file="BillingPayments.tsx" data-component-name="CreditCard" data-component-content="%7B%22className%22%3A%22w-5%20h-5%20text-primary%22%7D"><rect width="20" height="14" x="2" y="5" rx="2"></rect><line x1="2" x2="22" y1="10" y2="10"></line></svg>
            Current Retainer Plan<?php echo count($subscriptions) > 1 ? 's' : ''; ?>
        </h3>

        <div class="retainer-plans-grid">
            <div class="retainer-plans-container">
                <?php if (!empty($subscriptions)): ?>
                    <?php foreach ($subscriptions as $subscription): 
                        $subscription_status = $subscription->get_status();
                        if ($subscription_status !== 'active' && $subscription_status !== 'pending-cancel') continue;
                        
                        $next_payment_date = $subscription->get_date('next_payment');
                        $start_date = $subscription->get_date('start');
                        $total_paid = $subscription->get_total();
                        $renewal_total = $subscription->get_total();
                        
                        // Get purchased plan duration
                        $billing_interval = $subscription->get_billing_interval();
                        $billing_period = $subscription->get_billing_period();
                        $plan_duration = $billing_period === 'month' 
                            ? $billing_interval . ' Month' . ($billing_interval > 1 ? 's' : '') 
                            : $billing_interval . ' ' . ucfirst($billing_period) . ($billing_interval > 1 ? 's' : '');
                        
                        // Get short description of the first product in the subscription
                        $items = $subscription->get_items();
                        $short_description = '';
                        if (!empty($items)) {
                            $first_item = reset($items);
                            $product = $first_item->get_product();
                            if ($product) {
                                $short_description = $product->get_short_description();
                            }
                        }
                        $plan_name = esc_html(wp_strip_all_tags($short_description));

                        
                        // Calculate total investment (all completed orders for this subscription)
                        $related_orders = $subscription->get_related_orders('all', 'any');
                        $total_investment = 0;
                        foreach ($related_orders as $order_id) {
                            $order = wc_get_order($order_id);
                            if ($order && in_array($order->get_status(), ['completed', 'processing'])) {
                                $total_investment += $order->get_total();
                            }
                        }
                        
                        // Check if early renewal is available
                        $can_renew_early = false;
                        if ($next_payment_date) {
                            $days_until_renewal = (strtotime($next_payment_date) - time()) / (24 * 60 * 60);
                            $can_renew_early = $days_until_renewal <= 7; // Show "Pay Now" if within 7 days
                        }
                    ?>
                        <div class="retainer-plan-card">
                            <div class="plan-details">
                                                                <div class="plan-items">
                                    <?php foreach ($subscription->get_items() as $item): ?>
                                        <h4 class="plan-name" style="font-size: 1.6rem;"><?php echo esc_html($item->get_name()); ?></h4>
                                    <?php endforeach; ?>
                                </div>
                                <p class="plan-item" style="color: #999999; margin: -10px 0 0 0; padding: 0px;font-size: 1.1rem;font-weight: 500;"><?php echo $plan_name; ?></hp>

                            </div>
                            
                            <div class="plan-metrics">
                                <div class="plan-price">
                                    <?php echo wc_price($renewal_total); ?>
                                    <span class="price-period">per <?php echo $subscription->get_billing_period(); ?></span>
                                </div>
                                

                            </div>
                                                            <div class="plan-stats">
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo esc_html($plan_duration); ?></span>
                                        <span class="stat-label">Month<?php echo count($subscriptions) > 1 ? 's' : ''; ?> Active</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-value"><?php echo wc_price($total_investment); ?></span>
                                        <span class="stat-label">Total Investment</span>
                                    </div>
                                    <!--<div class="stat-item">-->
                                    <!--    <span class="stat-value"><?php echo number_format($subscription->get_payment_count()); ?>x</span>-->
                                    <!--    <span class="stat-label">Return on Investment</span>-->
                                    <!--</div>-->
                                                                   
                                    
                                </div>
                                <?php if ($can_renew_early): ?>
                                    <button class="btn-renew-now" data-subscription-id="<?php echo $subscription->get_id(); ?>">
                                        Pay Now
                                    </button>
                                <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="no-subscriptions">No active subscriptions found.</p>
                <?php endif; ?>
            </div>
            
            <!-- This Month Summary -->
            <div class="month-summary-card flex" style="flex-direction: column;align-items: center;justify-content: space-between;">
                <div class="flex;" style="flex-direction: column;">
                                    <div class="summary-icon flex" style="justify-content: center;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-dollar-sign w-5 h-5 text-primary" data-lov-id="src/components/portal/BillingPayments.tsx:77:14" data-lov-name="DollarSign" data-component-path="src/components/portal/BillingPayments.tsx" data-component-line="77" data-component-file="BillingPayments.tsx" data-component-name="DollarSign" data-component-content="%7B%22className%22%3A%22w-5%20h-5%20text-primary%22%7D"><line x1="12" x2="12" y1="2" y2="22"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg><h4 style="color: #ffffff; padding-left: 5px;margin-bottom: 0px;">This Month</h4>
                    
                </div>
                <div>                    <div class="summary-item">
                        <span class="summary-label">Total Spend</span>
                        <span class="summary-value"><?php echo wc_price($current_month_total); ?></span>
                    </div></div>
                </div>
                <div class="summary-details">
                    <?php if (!empty($subscriptions)): 
                        $next_subscription = reset($subscriptions);
                        $next_payment_date = $next_subscription ? $next_subscription->get_date('next_payment') : null;
                    ?>
                        <div class="summary-item">
                            <span class="summary-label">Next billing date</span>
                            <span class="summary-value"><?php echo $next_payment_date ? date('F j, Y', strtotime($next_payment_date)) : 'N/A'; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Investment by Service Area -->
    <div class="investment-section">
        <h3 class="section-title">Investment by Service Area</h3>
        <div class="category-breakdown">
            <?php 
            $colors = ['#12b886', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899'];
            $color_index = 0;
            foreach ($category_spending as $category => $amount): 
                $percentage = ($total_all_time_spending > 0) ? ($amount / $total_all_time_spending) * 100 : 0;
                $color = $colors[$color_index % count($colors)];
                $color_index++;
            ?>
                <div class="category-item">
                    <div class="category-header">
                        <span class="category-name"><?php echo esc_html($category); ?></span>
                        <span class="category-amount"><?php echo wc_price($amount); ?></span>
                        <span class="category-percentage"><?php echo '+' . round($percentage) . '%'; ?></span>
                    </div>
                    <div class="category-progress">
                        <div class="progress-bar" style="width: <?php echo $percentage; ?>%; background-color: <?php echo $color; ?>;"></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Payment History -->
    <div class="payment-history-section">
        <div class="section-header">
            <h3 class="section-title">Payment History</h3>
            <button id="export-all-invoices" class="btn-secondary">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" x2="12" y1="15" y2="3"></line>
                </svg>
                Export All
            </button>
        </div>
        
        <div class="invoices-list">
            <?php foreach ($customer_orders as $order): 
                $order_date = $order->get_date_created();
                $status = $order->get_status();
                $status_class = 'status-' . $status;
                $status_text = wc_get_order_status_name($status);
            ?>
                <div class="invoice-item">
                    <div class="invoice-status <?php echo $status_class; ?>">
                        <?php if ($status === 'completed'): ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="m9 12 2 2 4-4"></path>
                            </svg>
                        <?php else: ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" x2="12" y1="8" y2="12"></line>
                                <line x1="12" x2="12.01" y1="16" y2="16"></line>
                            </svg>
                        <?php endif; ?>
                    </div>
                    
                    <div class="invoice-details flex" style="flex-direction: column;">
                        <div class="invoice-number" style="font-size: 1.3rem;">INV-<?php echo $order->get_order_number(); ?></div>
                        <div class="flex" style="gap: 15px;">
                        <div class="invoice-date"><?php echo $order_date->date('Y-m-d'); ?></div>
                        <div class="invoice-type">
                            <?php 
                            $is_subscription = false;
                            if (function_exists('wcs_order_contains_subscription')) {
                                $is_subscription = wcs_order_contains_subscription($order);
                            }
                            echo $is_subscription ? 'Monthly Retainer' : 'One-time Payment';
                            ?>
                        </div>
                        </div>
                    </div>
                    
                    <div class="flex" style="flex-direction: column;gap: 4px;">
                     <div class="invoice-amount" style="font-size: 1.3rem;"><?php echo wc_price($order->get_total()); ?></div>
                    
                    <div class="invoice-status">
                        <span class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                    </div>
                    </div>
                    
                    <div class="invoice-actions">
                        <button class="btn-download-invoice" data-order-id="<?php echo $order->get_id(); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                <polyline points="7 10 12 15 17 10"></polyline>
                                <line x1="12" x2="12" y1="15" y2="3"></line>
                            </svg>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Payment Method Modal -->
<div id="payment-method-modal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Update Payment Method</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <div id="payment-method-form">
                <!-- This will be populated dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.billing-container {
    color: #ffffff;
    padding: 2rem;
    margin: 0 auto;
}

.billing-actions {
    margin-bottom: 2rem;
}

.btn-primary {
    background: #44da67;
    color: #000;
    padding: 12px 24px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-primary:hover {
    background: #3bc55a;
    transform: translateY(-2px);
}

.btn-secondary {
    background: transparent;
    color: #999;
    padding: 8px 16px;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: all 0.3s ease;
}

.btn-secondary:hover {
    color: #44da67;
    border-color: #44da67;
    background: #292929;
}

.section-title {
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Retainer Plans */
.retainer-plans-section {
    margin-bottom: 2rem;
    border-radius: 1rem;
    border: 1px solid #2E2E2E;
    padding: 2rem;
    color: #ffffff;
    background: #161616;
}

.retainer-plans-grid {
    display: grid;
    grid-template-columns: 1fr 300px;
    gap: 1.5rem;
}

.retainer-plans-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.retainer-plan-card {
    background: #161616;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 2rem;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 2rem;
    align-items: center;
}

.plan-name {
    font-size: 1.25rem;
    margin-bottom: 0.5rem;
}

.plan-items {
    color: #999;
}

.plan-item {
    margin: 0.25rem 0;
}

.plan-price {
    font-size: 1.6rem;
    font-weight: 700;
    color: #44da67;
    text-align: right;
}

.price-period {
    display: block;
    font-size: 1rem;
    font-weight: 500;
    color: #999;
        margin-top: 10px;
}

.plan-stats {
    display: flex;
    gap: 2rem;
    margin-top: 1rem;
        justify-content: space-between;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 1.25rem;
    font-weight: 600;
    color: #fff;
}

.stat-label {
    display: block;
    font-size: 0.75rem;
    color: #666;
    margin-top: 0.25rem;
}

.btn-renew-now {
    background: #44da67;
    color: #000;
    padding: 8px 20px;
    border: none;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 1rem;
    width: 100%;
}

/* Month Summary */
.month-summary-card {
    background: #161616;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 2rem;
    text-align: center;
}

.summary-icon {
    margin-bottom: 1rem;
    color: #44da67;
}

.summary-details {
    margin-top: 1.5rem;
}

.summary-item {
    margin-bottom: 1rem;
}

.summary-label {
    display: block;
    font-size: 0.875rem;
    color: #666;
    margin-bottom: 0.25rem;
}

.summary-value {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
}

/* Investment Section */
.investment-section {
    margin-bottom: 2rem;
    border-radius: 1rem;
    border: 1px solid #2E2E2E;
    padding: 2rem;
    color: #ffffff;
    background: #161616;
}

.category-breakdown {
    background: #161616;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    padding: 1.5rem;
}

.category-item {
    margin-bottom: 1.5rem;
}

.category-item:last-child {
    margin-bottom: 0;
}

.category-header {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.category-name {
    flex: 1;
    font-weight: 500;
}

.category-amount {
    font-weight: 600;
    margin-right: 1rem;
}

.category-percentage {
    font-size: 0.875rem;
    color: #44da67;
}

.category-progress {
    background: #2e2e2e;
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    transition: width 0.3s ease;
}

/* Payment History */
.payment-history-section {
    margin-bottom: 2rem;
    border-radius: 1rem;
    border: 1px solid #2E2E2E;
    padding: 2rem;
    color: #ffffff;
    background: #161616;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
}

.invoices-list {
    background: #161616;
    overflow: hidden;
}

.invoice-item {
    display: grid;
    grid-template-columns: 40px 1fr auto auto;
    align-items: center;
    gap: 1.5rem;
    padding: 1.5rem;
    border-bottom: 1px solid #2e2e2e;
    margin-bottom: 1rem;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
}

.invoice-status {
    display: flex;
    align-items: center;
    justify-content: center;
}

.invoice-status.status-completed {
    color: #44da67;
}

.invoice-status.status-pending,
.invoice-status.status-on-hold {
    color: #f59e0b;
}

.invoice-details {
    display: flex;
    gap: 2rem;
    align-items: center;
}

.invoice-number {
    font-weight: 600;
}

.invoice-date {
    color: #999;
    font-size: 0.9rem;
}

.invoice-type {
    color: #999;
    font-size: 0.9rem;
}

.invoice-amount {
    font-weight: 700;
    font-size: 1.125rem;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-completed {
    background: rgba(68, 218, 103, 0.1);
    color: #44da67;
}

.status-badge.status-pending,
.status-badge.status-on-hold {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.status-failed,
.status-badge.status-cancelled {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.btn-download-invoice {
    background: transparent;
    border: none;
    color: #999;
    cursor: pointer;
    padding: 8px;
    border-radius: 4px;
    transition: all 0.3s ease;
}

.btn-download-invoice:hover {
    color: #44da67;
    background: rgba(68, 218, 103, 0.1);
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
}

.modal-content {
    background: #1a1a1a;
    border: 1px solid #2e2e2e;
    border-radius: 10px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #2e2e2e;
}

.modal-header h3 {
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    color: #999;
    font-size: 1.5rem;
    cursor: pointer;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.1);
}

.modal-body {
    padding: 1.5rem;
}
    .invoice-details {
        flex-direction: column;
        gap: 5px;
        align-items: flex-start;
    }
/* Responsive */
@media (max-width: 768px) {
    .retainer-plans-grid {
        grid-template-columns: 1fr;
    }
    
    .retainer-plans-container {
        gap: 1.5rem;
    }
    
    .retainer-plan-card {
        grid-template-columns: 1fr;
    }
    
    .plan-stats {
        justify-content: space-between;
    }
    
    .invoice-item {
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .invoice-details {
        flex-direction: column;
        gap: 0.5rem;
        align-items: flex-start;
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // Update Payment Card
    $('#update-payment-card').on('click', function() {
        $('#payment-method-modal').fadeIn();
        
        // Load WooCommerce payment methods form
        $.ajax({
            url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
            type: 'POST',
            data: {
                action: 'get_payment_methods_form',
                nonce: '<?php echo esc_js(wp_create_nonce('payment_methods_nonce')); ?>'
            },
            success: function(response) {
                $('#payment-method-form').html(response);
            }
        });
    });
    
    // Close modal
    $('.modal-close').on('click', function() {
        $('#payment-method-modal').fadeOut();
    });

    $('.modal').on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $('#payment-method-modal').fadeOut();
        }
    });
    
    // Early renewal
    $('.btn-renew-now').on('click', function() {
        var subscriptionId = $(this).data('subscription-id');
        
        if (confirm('Process early renewal for this subscription?')) {
            $.ajax({
                url: <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>,
                type: 'POST',
                data: {
                    action: 'process_early_renewal',
                    subscription_id: subscriptionId,
                    nonce: '<?php echo esc_js(wp_create_nonce('early_renewal_nonce')); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Renewal processed successfully!');
                        location.reload();
                    } else {
                        alert('Error processing renewal: ' + response.data);
                    }
                }
            });
        }
    });
    
    // Download invoice
    $('.btn-download-invoice').on('click', function() {
        var orderId = $(this).data('order-id');
        window.location.href = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=download_invoice&order_id=' + orderId + '&nonce=<?php echo esc_js(wp_create_nonce('download_invoice_nonce')); ?>';
    });
    
    // Export all invoices
    $('#export-all-invoices').on('click', function() {
        window.location.href = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?> + '?action=export_all_invoices&nonce=<?php echo esc_js(wp_create_nonce('export_invoices_nonce')); ?>';
    });
});
</script>