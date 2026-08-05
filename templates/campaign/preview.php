<div id="mawiblah-preview" style="width: 100%; min-height: 500px; border: 1px solid #ddd; background: #fff;">
    <!-- Preview will be loaded here via JS -->
</div>
<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Trigger preview load on page load if function exists
        if (typeof MAWIBLAH_loadPreview === 'function') {
             MAWIBLAH_loadPreview();
        }
    });
</script>