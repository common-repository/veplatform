<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
?>
<script type='text/javascript'>
    (function () {
            var ve = document.createElement('script');
            ve.type = 'text/javascript';
            ve.async = true;
            ve.src = document.location.protocol + '<?php echo $api->getConfigOption('tag'); ?>';
            var s = document.getElementsByTagName('head')[0];
            s.appendChild(ve, s);
        })();
</script>