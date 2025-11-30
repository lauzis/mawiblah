<?php
use Mawiblah\Campaigns;
use Mawiblah\Templates;
?>
<section>
    <h2><?= __('Unsubscribe Growth (Last 12 Months)', 'mawiblah'); ?></h2>
    <div class="graph-wrap">
        <?php
        $growthStats = Campaigns::getUnsubscribeGrowthStats(12);
        
        $dataForDisplay = [
            __('Unsubscribes', 'mawiblah') => array_values($growthStats)
        ];
        
        Templates::loadTemplate('campaign/bar-graph.php', $dataForDisplay);
        ?>
    </div>
    
    <?php 
    $headers = [__('Month', 'mawiblah'), __('Unsubscribes', 'mawiblah')];
    $tableData = [];
    foreach ($growthStats as $month => $count) {
        $tableData[] = [$month, $count];
    }
    $tableData = array_reverse($tableData);
    
    ?>
    <div class="graph-wrap">
        <?php Templates::renderTable($headers, $tableData); ?>
    </div>
</section>