<?php
if (!defined('ABSPATH')) exit; // Exit if accessed directly
?>
<script id="veplatform-masterdata" type="text/javascript">
    var json = '<?php echo $api->getMasterData(); ?>';
    var veData = JSON.parse(json);
</script>
