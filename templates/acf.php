<div class="" style=";background: #161616;
    margin: 2rem;
    border-radius: 10px;
    border: 1px solid #2e2e2e;">
    <div class="common-padding">
        <?php
if (!empty($acf_name) && !empty($acf_content)) {
    
    echo wp_kses_post(wpautop($acf_content));
} else {
    echo '<p>No content found for this tab.</p>';
}
?>
    </div>

</div>