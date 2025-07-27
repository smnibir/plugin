<?php
if (!empty($acf_name) && !empty($acf_content)) {
    
    echo wp_kses_post(wpautop($acf_content));
} else {
    echo '<p>No content found for this tab.</p>';
}
?>
