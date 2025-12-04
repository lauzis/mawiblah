<?php

$headers = $data['headers'];
$rows = $data['rows'];

if (!isset($headers) || !isset($rows) || !is_array($headers) || !is_array($rows)): ?>
    <div class="mawiblah-table-container">
        <?php _e('No data to display.', 'mawiblah'); ?>
    </div>
    <?php return; ?>
<?php endif; ?>

<div class="mawiblah-table-container">
    <table class="mawiblah-stats-table">
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <th><?= esc_html($header); ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <?php foreach ($row as $cell): ?>
                        <td><?= esc_html($cell); ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<style>
    .mawiblah-table-container {
        width: 100%;
        overflow-x: auto;
        background-color: #FFF;
        border-radius: 6px;
    }

    .mawiblah-stats-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
    }

    .mawiblah-stats-table thead tr {
        background-color: #F6F7F7;
    }

    .mawiblah-stats-table thead th {
        padding: 15px 20px;
        text-align: left;
        font-size: 14px;
        font-weight: 600;
        color: #23282d;
        border-bottom: 2px solid #ddd;
        white-space: nowrap;
    }

    .mawiblah-stats-table tbody tr {
        transition: background-color 0.2s ease;
    }

    .mawiblah-stats-table tbody tr:nth-child(even) {
        background-color: #F9F9F9;
    }

    .mawiblah-stats-table tbody tr:hover {
        background-color: #F0F6FC;
    }

    .mawiblah-stats-table tbody td {
        padding: 12px 20px;
        border-bottom: 1px solid #E5E5E5;
        font-size: 14px;
        color: #555;
    }

    .mawiblah-stats-table tbody tr:last-child td {
        border-bottom: none;
    }

    .mawiblah-stats-table td:first-child,
    .mawiblah-stats-table th:first-child {
        padding-left: 20px;
    }

    .mawiblah-stats-table td:last-child,
    .mawiblah-stats-table th:last-child {
        padding-right: 20px;
    }

    @media screen and (max-width: 782px) {
        .mawiblah-stats-table thead th,
        .mawiblah-stats-table tbody td {
            padding: 10px 15px;
            font-size: 13px;
        }
    }
</style>
