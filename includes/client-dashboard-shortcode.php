<?php
// Shortcode to display ClickUp Client Portal Tabs
function render_clickup_client_dashboard() {
    $user_id = get_current_user_id();
    $doc_id = get_field('client_portal', 'user_' . $user_id);
    $workspace_id = get_option('clickup_workspace_id');
    $api_key = get_option('clickup_api_key');

    if (!$doc_id || !$workspace_id || !$api_key) {
        return '<p>Missing ClickUp setup information.</p>';
    }

    $response = wp_remote_get("https://api.clickup.com/api/v3/workspaces/{$workspace_id}/docs/{$doc_id}/pages", [
        'headers' => ['Authorization' => $api_key],
    ]);

    if (is_wp_error($response)) {
        return '<p>Error fetching ClickUp pages.</p>';
    }

    $pages = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($pages)) {
        return '<p>No pages found under this Client Portal.</p>';
    }

    $allowed_titles = [
        'Welcome',
        'Meeting Notes',
        'Task List',
        'Performance Summary',
        'Analytics Dashboard',
        'Campaign Strategy',
        'Billing & Payments',
        'Brand Assets & Info',
        'Support Form'
    ];

    $template_map = [
        'Welcome'               => 'welcome.php',
        'Meeting Notes'         => 'meeting-notes.php',
        'Task List'             => 'task-list.php',
        'Performance Summary'   => 'performance-summary.php',
        'Analytics Dashboard'   => 'iframe.php',
        'Campaign Strategy'     => 'campaign-strategy.php',
        'Billing & Payments'    => 'bill.php',
        'Brand Assets & Info'   => 'brand.php',
        'Support Form'          => 'iframe-support.php'
    ];

    $icons = [
        'Welcome' => 'home.svg',
        'Meeting Notes' => 'notes.svg',
        'Task List' => 'task.svg',
        'Performance Summary' => 'chart.svg',
        'Analytics Dashboard' => 'analytics.svg',
        'Campaign Strategy' => 'target.svg',
        'Billing & Payments' => 'card.svg',
        'Brand Assets & Info' => 'brand.svg',
        'Support Form' => 'support.svg'
    ];

    $filtered_pages = array_filter($pages, function ($page) use ($allowed_titles) {
        return in_array($page['name'], $allowed_titles);
    });

    usort($filtered_pages, function ($a, $b) use ($allowed_titles) {
        return array_search($a['name'], $allowed_titles) - array_search($b['name'], $allowed_titles);
    });

    // Add manual tabs if not in API
    $api_titles = wp_list_pluck($filtered_pages, 'name');
    foreach ($allowed_titles as $title) {
        if (!in_array($title, $api_titles) && $title !== 'Support Form') {
            $filtered_pages[] = ['name' => $title, 'content' => ''];
        }
    }

    $acf_tabs = get_field('add_custom_data', 'user_' . $user_id) ?: [];

    require_once plugin_dir_path(__DIR__) . 'includes/parsedown.php';
    $Parsedown = new Parsedown();

    // User profile data using ACF fields
    $user = get_userdata($user_id);
    $first_name = get_field('first_name', 'user_' . $user_id) ?: $user->first_name;
    $last_name = get_field('last_name', 'user_' . $user_id) ?: $user->last_name;
    $user_name = trim($first_name . ' ' . $last_name);
    $profile_image = get_field('profile_image', 'user_' . $user_id); // ACF image field
    $company_name = get_field('company_name', 'user_' . $user_id) ?: 'TechStart Inc.';
    $initial = $profile_image ? '' : (substr($first_name, 0, 1) . substr($last_name, 0, 1)); // Initials if no image

    ob_start();
    ?>
    <div class="clickup-dashboard">
        <div class="clickup-sidebar">
            <div class="wg-branding flex">
                <div><img src="https://assets.webgrowth.io/wp-content/uploads/GrowthEngine-Admin-Panel.png"></div>
                <div class="flex" style="flex-direction: column;">
                    <span style="font-size: 1.25rem;line-height: 1.8rem;font-weight: 700;letter-spacing: -.025em;">Webgrowth</span>
                    <span style="font-weight: 500;font-size: .8rem;line-height: 1rem;letter-spacing: -.025em;">Client Portal</span>
                </div>
            </div>
            <ul>
                <?php
                $tab_index = 0;
                foreach ($allowed_titles as $title) {
                    $icon = $icons[$title] ?? '';
                    $svg_path = plugin_dir_path(__DIR__) . 'assets/svg/' . $icon;
                    $svg_content = file_exists($svg_path) ? file_get_contents($svg_path) : '';
                    echo "<li data-tab='clickup-tab-{$tab_index}'><span class='icon'>{$svg_content}</span> <span class='text-icon'>" . esc_html($title) . "</span></li>";
                    $tab_index++;

                    if ($title === 'Support Form' && $acf_tabs) {
                        foreach ($acf_tabs as $acf) {
                            echo "<li data-tab='clickup-tab-{$tab_index}'><span class='icon'>🛠️</span> " . esc_html($acf['tab_name']) . "</li>";
                            $tab_index++;
                        }
                    }
                }
                ?>
            </ul>
            <!-- User Profile Section at Bottom -->
            <div class="flex user-profile-section" style="position: absolute; bottom: 0rem; width: calc(100% - 3rem); border-top:1px solid #2e2e2e;">
                <div class="flex user-profile" style="align-items: center; padding: .75rem 1rem; cursor: pointer; width: 100%;">
                    <div class="profile-circle flex" style="color:#000000;width: 40px; height: 40px; border-radius: 50%; align-items: center; justify-content: center; font-weight: bold; margin-right: 10px; <?php echo !$profile_image ? 'background: #44da67;' : ''; ?>">
                        <?php
                        if ($profile_image) {
                            echo '<img src="' . esc_url($profile_image['url']) . '" alt="' . esc_attr($user_name) . '" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
                        } else {
                            echo esc_html($initial);
                        }
                        ?>
                    </div>
                    <div class="flex" style="flex-direction: column; flex-grow: 1;">
                        <span class="text-icon" style="font-size: 1rem;margin: -5px 0"><?php echo esc_html($user_name); ?></span>
                        <span class="text-icon" style="font-size: .8rem; color: #999999;"><?php echo esc_html($company_name); ?></span>
                    </div>
                    <div class="dropdown-arrow flex" id="dropdown-arrow" style="font-size: 1.2rem; transition: transform 0.3s;"><svg style="fill: #ffffff;width: 2rem; height: 2rem;" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><path d="M297.4 201.4C309.9 188.9 330.2 188.9 342.7 201.4L502.7 361.4C515.2 373.9 515.2 394.2 502.7 406.7C490.2 419.2 469.9 419.2 457.4 406.7L320 269.3L182.6 406.6C170.1 419.1 149.8 419.1 137.3 406.6C124.8 394.1 124.8 373.8 137.3 361.3L297.3 201.3z"/></svg></div>
                </div>
                <div class="dropdown-menu" id="dropdown-menu" style="display: none; position: absolute; bottom: 100%; left: 0; background: #2e2e2e; border-radius: .75rem; width: 100%; box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2); z-index: 1000; margin-top: .75rem;">
                    <a href="#" class="dropdown-item flex" data-action="logout" style="padding: .75rem 1rem; color: #ffffff; text-decoration: none; display: block;">Logout</a>
                    <a href="/edit-profile" class="dropdown-item flex" data-action="edit-profile" style="padding: .75rem 1rem; color: #ffffff; text-decoration: none; display: block;">Update Profile</a>
                    <a href="/update-information" class="dropdown-item flex" data-action="update-company-information" style="padding: .75rem 1rem; color: #ffffff; text-decoration: none; display: block;">Update Company Information</a>
                </div>
            </div>
        </div>

        <div class="clickup-content-area">
            <?php
            $tab_index = 0;
            foreach ($allowed_titles as $title) {
                echo "<div id='clickup-tab-{$tab_index}' class='clickup-tab' style='display: none;'>";

                $page = current(array_filter($filtered_pages, fn($p) => $p['name'] === $title));
                $template = $template_map[$title] ?? null;

                // ✅ Always define $content for templates
                $content = $page['content'] ?? '';

                // ✅ Only define $iframe_url if iframe.php is used
                if ($template === 'iframe.php') {
                    preg_match('/https?:\/\/[^\s"]+/', $content, $matches);
                    $iframe_url = $matches[0] ?? '';
                }
                if ($template === 'iframe-support.php') {
                    preg_match('/https?:\/\/[^\s"]+/', $content, $matches);
                    $iframe_url = $matches[0] ?? '';
                }

                $path = plugin_dir_path(__DIR__) . 'templates/' . $template;
                if ($template && file_exists($path)) {
                    include $path;
                } else {
                    echo "<p>Template missing for: " . esc_html($title) . "</p>";
                }

                echo "</div>";
                $tab_index++;

                if ($title === 'Support Form' && $acf_tabs) {
                    foreach ($acf_tabs as $acf) {
                        echo "<div id='clickup-tab-{$tab_index}' class='clickup-tab' style='display: none;'>";
                        $acf_name = $acf['tab_name'];
                        $acf_content = $acf['details'];
                        include plugin_dir_path(__DIR__) . 'templates/acf.php';
                        echo "</div>";
                        $tab_index++;
                    }
                }
            }
            ?>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabs = document.querySelectorAll('.clickup-sidebar li');
            const contents = document.querySelectorAll('.clickup-tab');

            if (tabs.length && contents.length) {
                tabs[0].classList.add('active');
                contents[0].style.display = 'block';
            }

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () {
                    tabs.forEach(el => el.classList.remove('active'));
                    contents.forEach(el => el.style.display = 'none');

                    tab.classList.add('active');
                    const target = document.getElementById(tab.dataset.tab);
                    if (target) target.style.display = 'block';
                });
            });

            // User Profile Dropdown
            const userProfile = document.querySelector('.user-profile');
            const dropdownMenu = document.getElementById('dropdown-menu');
            const dropdownArrow = document.getElementById('dropdown-arrow');

            userProfile.addEventListener('click', function (e) {
                e.preventDefault();
                const isOpen = dropdownMenu.style.display === 'block';
                dropdownMenu.style.display = isOpen ? 'none' : 'block';
                dropdownArrow.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(180deg)';
            });

            document.addEventListener('click', function (e) {
                if (!userProfile.contains(e.target)) {
                    dropdownMenu.style.display = 'none';
                    dropdownArrow.style.transform = 'rotate(0deg)';
                }
            });

            // Handle dropdown item clicks
            document.querySelectorAll('.dropdown-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    e.preventDefault();
                    const action = this.getAttribute('data-action');

                    switch (action) {
                        case 'logout':
                            // Implement logout logic (e.g., redirect to WP logout URL)
                            window.location.href = '<?php echo wp_logout_url(); ?>';
                            break;
                        case 'edit-profile':
                            window.location.href = '/edit-profile';
                            break;
                        case 'update-company-information':
                            window.location.href = '/update-information';
                            break;
                    }
                    dropdownMenu.style.display = 'none';
                    dropdownArrow.style.transform = 'rotate(0deg)';
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('client_dashboard', 'render_clickup_client_dashboard');