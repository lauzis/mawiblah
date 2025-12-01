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
    
    <?php
    $reasons = \Mawiblah\Subscribers::getUnsubscribeReasons(20);
    if (!empty($reasons)) {
        ?>
        <h3><?= __('Latest Unsubscribe Reasons', 'mawiblah'); ?></h3>
        <div class="graph-wrap">
            <?php
            $reasonHeaders = [__('Date', 'mawiblah'), __('Reason', 'mawiblah')];
            $reasonData = [];
            foreach ($reasons as $reason) {
                $reasonData[] = [$reason['date'], esc_html($reason['feedback'])];
            }
            Templates::renderTable($reasonHeaders, $reasonData);
            ?>
        </div>
        <?php
    }
    ?>
</section>