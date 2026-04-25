<?php
/**
 * Data Exporter
 * Provides comprehensive data export functionality for charts, tables, and filtered datasets
 * Supports CSV, Excel, and PDF export formats with metadata inclusion
 * Requirements: 8.1, 8.2, 8.4, 8.6
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/AnalyticsService.php';
require_once __DIR__ . '/ExportTemplateManager.php';

class DataExporter
{
    private $db;
    private $conn;
    private $analyticsService;
    private $templateManager;
    
    // Export formats
    const FORMAT_CSV = 'csv';
    const FORMAT_EXCEL = 'xlsx';
    const FORMAT_PDF = 'pdf';
    
    // Export types
    const TYPE_CHART = 'chart';
    const TYPE_TABLE = 'table';
    const TYPE_FILTERED_DATASET = 'filtered_dataset';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->analyticsService = new AnalyticsService();
        $this->templateManager = new ExportTemplateManager();
    }
    
    /**
     * Export chart data with visual formatting
     * 
     * @param string $chartId Chart identifier
     * @param string $format Export format (csv, xlsx, pdf)
     * @param array $options Export options
     * @return array Export result with file path and metadata
     */
    public function exportChart($chartId, $format, $options = [])
    {
        // Validate format
        if (!in_array($format, [self::FORMAT_CSV, self::FORMAT_EXCEL, self::FORMAT_PDF])) {
            throw new Exception("Invalid export format: {$format}");
        }
        
        // Get chart data
        $chartData = $this->getChartData($chartId, $options);
        
        // Add metadata
        $metadata = $this->generateMetadata(self::TYPE_CHART, $options);
        $metadata['chart_id'] = $chartId;
        $metadata['chart_type'] = $chartData['type'] ?? 'unknown';
        
        // Export based on format
        switch ($format) {
            case self::FORMAT_CSV:
                $filepath = $this->exportChartToCSV($chartData, $metadata, $options);
                break;
            case self::FORMAT_EXCEL:
                $filepath = $this->exportChartToExcel($chartData, $metadata, $options);
                break;
            case self::FORMAT_PDF:
                $filepath = $this->exportChartToPDF($chartData, $metadata, $options);
                break;
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'metadata' => $metadata,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Export table data
     * 
     * @param array $data Table data
     * @param string $format Export format
     * @param array $options Export options
     * @return array Export result
     */
    public function exportTable($data, $format, $options = [])
    {
        // Validate format
        if (!in_array($format, [self::FORMAT_CSV, self::FORMAT_EXCEL, self::FORMAT_PDF])) {
            throw new Exception("Invalid export format: {$format}");
        }
        
        // Validate data
        if (empty($data)) {
            throw new Exception("No data to export");
        }
        
        // Add metadata
        $metadata = $this->generateMetadata(self::TYPE_TABLE, $options);
        $metadata['row_count'] = count($data);
        $metadata['column_count'] = count($data[0] ?? []);
        
        // Export based on format
        switch ($format) {
            case self::FORMAT_CSV:
                $filepath = $this->exportTableToCSV($data, $metadata, $options);
                break;
            case self::FORMAT_EXCEL:
                $filepath = $this->exportTableToExcel($data, $metadata, $options);
                break;
            case self::FORMAT_PDF:
                $filepath = $this->exportTableToPDF($data, $metadata, $options);
                break;
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'metadata' => $metadata,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Export filtered dataset
     * 
     * @param array $filters Applied filters
     * @param string $format Export format
     * @param array $options Export options
     * @return array Export result
     */
    public function exportFilteredDataset($filters, $format, $options = [])
    {
        // Validate format
        if (!in_array($format, [self::FORMAT_CSV, self::FORMAT_EXCEL, self::FORMAT_PDF])) {
            throw new Exception("Invalid export format: {$format}");
        }
        
        // Get filtered data based on dataset type
        $datasetType = $options['dataset_type'] ?? 'members';
        $data = $this->getFilteredData($datasetType, $filters);
        
        // Add metadata
        $metadata = $this->generateMetadata(self::TYPE_FILTERED_DATASET, $options);
        $metadata['filters'] = $filters;
        $metadata['dataset_type'] = $datasetType;
        $metadata['row_count'] = count($data);
        
        // Export based on format
        switch ($format) {
            case self::FORMAT_CSV:
                $filepath = $this->exportDatasetToCSV($data, $metadata, $options);
                break;
            case self::FORMAT_EXCEL:
                $filepath = $this->exportDatasetToExcel($data, $metadata, $options);
                break;
            case self::FORMAT_PDF:
                $filepath = $this->exportDatasetToPDF($data, $metadata, $options);
                break;
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'metadata' => $metadata,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Export dataset with progress tracking
     * 
     * @param string $datasetType Dataset type (members, contributions, centers)
     * @param array $data Dataset data
     * @param string $format Export format
     * @param callable $progressCallback Progress callback function
     * @return array Export result
     */
    public function exportDataset($datasetType, $data, $format, $progressCallback = null)
    {
        // Validate format
        if (!in_array($format, [self::FORMAT_CSV, self::FORMAT_EXCEL, self::FORMAT_PDF])) {
            throw new Exception("Invalid export format: {$format}");
        }
        
        // Validate data
        if (empty($data)) {
            throw new Exception("No data to export");
        }
        
        // Generate metadata
        $metadata = [
            'export_type' => self::TYPE_FILTERED_DATASET,
            'export_date' => date('Y-m-d H:i:s'),
            'export_timestamp' => time(),
            'dataset_type' => $datasetType,
            'row_count' => count($data),
            'column_count' => count($data[0] ?? []),
            'format' => $format
        ];
        
        // Track progress
        $totalRows = count($data);
        $processedRows = 0;
        
        // Export based on format with progress tracking
        $filename = $this->generateFilename($datasetType, $format, []);
        $filepath = $this->getExportPath($filename);
        
        // Call progress callback at start
        if ($progressCallback && is_callable($progressCallback)) {
            $progressCallback(0);
        }
        
        // Perform export with chunked processing for large datasets
        $chunkSize = 1000;
        $chunks = array_chunk($data, $chunkSize);
        $output = fopen($filepath, 'w');
        
        // Write metadata header
        fputcsv($output, ['Dataset Export']);
        fputcsv($output, ['Dataset Type', $datasetType]);
        fputcsv($output, ['Export Date', $metadata['export_date']]);
        fputcsv($output, ['Row Count', $metadata['row_count']]);
        fputcsv($output, []); // Empty line
        
        // Write headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);
        }
        
        // Write data in chunks with progress updates
        foreach ($chunks as $chunkIndex => $chunk) {
            foreach ($chunk as $row) {
                fputcsv($output, $row);
                $processedRows++;
            }
            
            // Update progress
            if ($progressCallback && is_callable($progressCallback)) {
                $progress = ($processedRows / $totalRows) * 100;
                $progressCallback($progress);
            }
        }
        
        fclose($output);
        
        // Final progress update
        if ($progressCallback && is_callable($progressCallback)) {
            $progressCallback(100);
        }
        
        return [
            'success' => true,
            'file_path' => $filepath,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'metadata' => $metadata,
            'size' => filesize($filepath),
            'rows_exported' => $processedRows
        ];
    }
    
    /**
     * Stream export for large datasets
     * Uses generator pattern to minimize memory usage
     * 
     * @param string $datasetType Dataset type
     * @param array $filters Filters to apply
     * @param string $format Export format
     * @param callable $progressCallback Progress callback
     * @return array Export result
     */
    public function streamExport($datasetType, $filters, $format, $progressCallback = null)
    {
        // Validate format
        if (!in_array($format, [self::FORMAT_CSV, self::FORMAT_EXCEL, self::FORMAT_PDF])) {
            throw new Exception("Invalid export format: {$format}");
        }
        
        // Generate filename and path
        $filename = $this->generateFilename($datasetType, $format, ['stream' => true]);
        $filepath = $this->getExportPath($filename);
        
        // Open output file
        $output = fopen($filepath, 'w');
        
        // Write metadata header
        fputcsv($output, ['Streamed Dataset Export']);
        fputcsv($output, ['Dataset Type', $datasetType]);
        fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
        fputcsv($output, []); // Empty line
        
        // Get data stream based on dataset type
        $dataStream = null;
        $rowCount = 0;
        $headerWritten = false;
        
        switch ($datasetType) {
            case 'members':
                $dataStream = $this->analyticsService->streamMemberAnalytics($filters);
                break;
            case 'contributions':
                $dataStream = $this->analyticsService->streamContributionAnalytics($filters);
                break;
            default:
                fclose($output);
                throw new Exception("Unsupported dataset type for streaming: {$datasetType}");
        }
        
        // Process stream with progress tracking
        $batchSize = 100;
        $batchCount = 0;
        
        if ($progressCallback && is_callable($progressCallback)) {
            $progressCallback(0, 0, 'Starting export...');
        }
        
        foreach ($dataStream as $row) {
            // Write headers from first row
            if (!$headerWritten) {
                fputcsv($output, array_keys($row));
                $headerWritten = true;
            }
            
            // Write data row
            fputcsv($output, $row);
            $rowCount++;
            $batchCount++;
            
            // Update progress every batch
            if ($batchCount >= $batchSize) {
                if ($progressCallback && is_callable($progressCallback)) {
                    $progressCallback(null, $rowCount, "Exported {$rowCount} rows...");
                }
                $batchCount = 0;
                
                // Free memory
                gc_collect_cycles();
            }
        }
        
        fclose($output);
        
        // Final progress update
        if ($progressCallback && is_callable($progressCallback)) {
            $progressCallback(100, $rowCount, "Export complete: {$rowCount} rows");
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'rows_exported' => $rowCount,
            'size' => filesize($filepath),
            'metadata' => [
                'export_type' => 'streamed',
                'dataset_type' => $datasetType,
                'export_date' => date('Y-m-d H:i:s'),
                'row_count' => $rowCount
            ]
        ];
    }
    
    /**
     * Export data using a custom template
     * 
     * @param string $templateId Template ID
     * @param array $data Data to export
     * @param array $options Export options
     * @return array Export result
     */
    public function exportWithTemplate($templateId, $data, $options = [])
    {
        // Get template
        $template = $this->templateManager->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        // Apply template to data
        $templateResult = $this->templateManager->applyTemplate($templateId, $data);
        $formattedData = $templateResult['formatted_data'];
        
        // Export formatted data
        $format = $template['format'];
        $exportType = $template['type'];
        
        // Add template metadata to options
        $options['template_id'] = $templateId;
        $options['template_name'] = $template['name'];
        
        // Export based on type
        switch ($exportType) {
            case ExportTemplateManager::TYPE_TABLE:
            case ExportTemplateManager::TYPE_DATASET:
                $result = $this->exportTable($formattedData, $format, $options);
                break;
            case ExportTemplateManager::TYPE_CHART:
                // For charts, format data as chart data structure
                $chartData = $this->formatAsChartData($formattedData, $template);
                $result = $this->exportChartData($chartData, $format, $options);
                break;
            default:
                throw new Exception("Unsupported template type: {$exportType}");
        }
        
        // Add template information to result
        $result['template_applied'] = true;
        $result['template_id'] = $templateId;
        $result['template_name'] = $template['name'];
        
        return $result;
    }
    
    /**
     * Format data as chart data structure
     */
    private function formatAsChartData($data, $template)
    {
        $config = $template['configuration'];
        
        return [
            'type' => $config['chart_type'] ?? 'bar',
            'labels' => array_column($data, $config['label_column'] ?? array_keys($data[0])[0]),
            'datasets' => [
                [
                    'label' => $config['dataset_label'] ?? 'Data',
                    'data' => array_column($data, $config['data_column'] ?? array_keys($data[0])[1])
                ]
            ]
        ];
    }
    
    /**
     * Export chart data (helper method)
     */
    private function exportChartData($chartData, $format, $options)
    {
        $metadata = $this->generateMetadata(self::TYPE_CHART, $options);
        $metadata['chart_type'] = $chartData['type'] ?? 'unknown';
        
        switch ($format) {
            case self::FORMAT_CSV:
                $filepath = $this->exportChartToCSV($chartData, $metadata, $options);
                break;
            case self::FORMAT_EXCEL:
                $filepath = $this->exportChartToExcel($chartData, $metadata, $options);
                break;
            case self::FORMAT_PDF:
                $filepath = $this->exportChartToPDF($chartData, $metadata, $options);
                break;
        }
        
        return [
            'success' => true,
            'filepath' => $filepath,
            'filename' => basename($filepath),
            'format' => $format,
            'metadata' => $metadata,
            'size' => filesize($filepath)
        ];
    }
    
    /**
     * Bulk export multiple items with progress tracking
     * 
     * @param array $exportItems Array of export items
     * @param callable $progressCallback Progress callback function
     * @return array Bulk export results
     */
    public function bulkExport($exportItems, $progressCallback = null)
    {
        $results = [];
        $totalItems = count($exportItems);
        $completedItems = 0;
        
        foreach ($exportItems as $index => $item) {
            try {
                // Export based on item type
                switch ($item['type']) {
                    case self::TYPE_CHART:
                        $result = $this->exportChart(
                            $item['chart_id'],
                            $item['format'],
                            $item['options'] ?? []
                        );
                        break;
                    case self::TYPE_TABLE:
                        $result = $this->exportTable(
                            $item['data'],
                            $item['format'],
                            $item['options'] ?? []
                        );
                        break;
                    case self::TYPE_FILTERED_DATASET:
                        $result = $this->exportFilteredDataset(
                            $item['filters'],
                            $item['format'],
                            $item['options'] ?? []
                        );
                        break;
                    default:
                        throw new Exception("Unknown export type: {$item['type']}");
                }
                
                $results[] = $result;
                $completedItems++;
                
                // Call progress callback
                if ($progressCallback && is_callable($progressCallback)) {
                    $progress = ($completedItems / $totalItems) * 100;
                    $progressCallback($progress, $completedItems, $totalItems, $result);
                }
                
            } catch (Exception $e) {
                $results[] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                    'item' => $item
                ];
                $completedItems++;
                
                // Call progress callback even on error
                if ($progressCallback && is_callable($progressCallback)) {
                    $progress = ($completedItems / $totalItems) * 100;
                    $progressCallback($progress, $completedItems, $totalItems, null);
                }
            }
        }
        
        return [
            'success' => true,
            'total_items' => $totalItems,
            'completed_items' => $completedItems,
            'results' => $results
        ];
    }
    
    /**
     * Generate export metadata
     * 
     * @param string $exportType Export type
     * @param array $options Export options
     * @return array Metadata
     */
    private function generateMetadata($exportType, $options)
    {
        return [
            'export_type' => $exportType,
            'export_date' => date('Y-m-d H:i:s'),
            'export_timestamp' => time(),
            'user_id' => $options['user_id'] ?? null,
            'user_name' => $options['user_name'] ?? 'System',
            'filters_applied' => $options['filters'] ?? [],
            'parameters' => $options['parameters'] ?? [],
            'version' => '1.0'
        ];
    }
    
    /**
     * Get chart data by ID
     * 
     * @param string $chartId Chart identifier
     * @param array $options Options
     * @return array Chart data
     */
    private function getChartData($chartId, $options)
    {
        // Map chart IDs to data retrieval methods
        $chartMap = [
            'member_registration_trend' => 'getMemberRegistrationTrend',
            'gender_distribution' => 'getGenderDistribution',
            'age_distribution' => 'getAgeDistribution',
            'contribution_trend' => 'getContributionTrend',
            'payment_status' => 'getPaymentStatusDistribution',
            'center_performance' => 'getCenterPerformance'
        ];
        
        if (!isset($chartMap[$chartId])) {
            throw new Exception("Unknown chart ID: {$chartId}");
        }
        
        $method = $chartMap[$chartId];
        return $this->$method($options);
    }
    
    /**
     * Get filtered data based on dataset type
     * 
     * @param string $datasetType Dataset type
     * @param array $filters Filters
     * @return array Filtered data
     */
    private function getFilteredData($datasetType, $filters)
    {
        switch ($datasetType) {
            case 'members':
                return $this->analyticsService->getMemberAnalytics($filters);
            case 'contributions':
                return $this->analyticsService->getContributionAnalytics($filters);
            case 'centers':
                return $this->analyticsService->getCenterAnalytics($filters);
            default:
                throw new Exception("Unknown dataset type: {$datasetType}");
        }
    }
    
    /**
     * Export chart to CSV format
     * 
     * @param array $chartData Chart data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportChartToCSV($chartData, $metadata, $options)
    {
        $filename = $this->generateFilename('chart', 'csv', $options);
        $filepath = $this->getExportPath($filename);
        
        $output = fopen($filepath, 'w');
        
        // Write metadata header
        fputcsv($output, ['Chart Export']);
        fputcsv($output, ['Chart ID', $metadata['chart_id']]);
        fputcsv($output, ['Chart Type', $metadata['chart_type']]);
        fputcsv($output, ['Export Date', $metadata['export_date']]);
        fputcsv($output, ['Exported By', $metadata['user_name']]);
        fputcsv($output, []); // Empty line
        
        // Write chart data
        if (isset($chartData['labels']) && isset($chartData['datasets'])) {
            // Write headers
            $headers = array_merge(['Label'], array_column($chartData['datasets'], 'label'));
            fputcsv($output, $headers);
            
            // Write data rows
            foreach ($chartData['labels'] as $index => $label) {
                $row = [$label];
                foreach ($chartData['datasets'] as $dataset) {
                    $row[] = $dataset['data'][$index] ?? '';
                }
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        return $filepath;
    }
    
    /**
     * Export chart to Excel format
     * 
     * @param array $chartData Chart data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportChartToExcel($chartData, $metadata, $options)
    {
        // For now, use CSV format as Excel
        // In production, use PhpSpreadsheet library
        $filename = $this->generateFilename('chart', 'xlsx', $options);
        $filepath = $this->getExportPath($filename);
        
        // Copy CSV content to Excel file
        $csvPath = $this->exportChartToCSV($chartData, $metadata, $options);
        copy($csvPath, $filepath);
        unlink($csvPath);
        
        return $filepath;
    }
    
    /**
     * Export chart to PDF format
     * 
     * @param array $chartData Chart data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportChartToPDF($chartData, $metadata, $options)
    {
        $filename = $this->generateFilename('chart', 'pdf', $options);
        $filepath = $this->getExportPath($filename);
        
        // Generate PDF content (simplified)
        // In production, use TCPDF library with chart images
        $content = "WDB Analytics - Chart Export\n\n";
        $content .= "Chart ID: " . $metadata['chart_id'] . "\n";
        $content .= "Chart Type: " . $metadata['chart_type'] . "\n";
        $content .= "Export Date: " . $metadata['export_date'] . "\n";
        $content .= "Exported By: " . $metadata['user_name'] . "\n\n";
        
        if (isset($chartData['labels']) && isset($chartData['datasets'])) {
            $content .= "Chart Data:\n";
            foreach ($chartData['labels'] as $index => $label) {
                $content .= $label . ": ";
                foreach ($chartData['datasets'] as $dataset) {
                    $content .= $dataset['label'] . "=" . ($dataset['data'][$index] ?? 'N/A') . " ";
                }
                $content .= "\n";
            }
        }
        
        file_put_contents($filepath, $content);
        return $filepath;
    }
    
    /**
     * Export table to CSV format
     * 
     * @param array $data Table data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportTableToCSV($data, $metadata, $options)
    {
        $filename = $this->generateFilename('table', 'csv', $options);
        $filepath = $this->getExportPath($filename);
        
        $output = fopen($filepath, 'w');
        
        // Write metadata header
        fputcsv($output, ['Table Export']);
        fputcsv($output, ['Export Date', $metadata['export_date']]);
        fputcsv($output, ['Exported By', $metadata['user_name']]);
        fputcsv($output, ['Row Count', $metadata['row_count']]);
        fputcsv($output, []); // Empty line
        
        // Write table headers
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);
            
            // Write data rows
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        return $filepath;
    }
    
    /**
     * Export table to Excel format
     * 
     * @param array $data Table data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportTableToExcel($data, $metadata, $options)
    {
        // For now, use CSV format as Excel
        // In production, use PhpSpreadsheet library
        $filename = $this->generateFilename('table', 'xlsx', $options);
        $filepath = $this->getExportPath($filename);
        
        $csvPath = $this->exportTableToCSV($data, $metadata, $options);
        copy($csvPath, $filepath);
        unlink($csvPath);
        
        return $filepath;
    }
    
    /**
     * Export table to PDF format
     * 
     * @param array $data Table data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportTableToPDF($data, $metadata, $options)
    {
        $filename = $this->generateFilename('table', 'pdf', $options);
        $filepath = $this->getExportPath($filename);
        
        // Generate PDF content (simplified)
        $content = "WDB Analytics - Table Export\n\n";
        $content .= "Export Date: " . $metadata['export_date'] . "\n";
        $content .= "Exported By: " . $metadata['user_name'] . "\n";
        $content .= "Row Count: " . $metadata['row_count'] . "\n\n";
        
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $content .= implode("\t", $headers) . "\n";
            $content .= str_repeat("-", 80) . "\n";
            
            foreach ($data as $row) {
                $content .= implode("\t", $row) . "\n";
            }
        }
        
        file_put_contents($filepath, $content);
        return $filepath;
    }
    
    /**
     * Export dataset to CSV format
     * 
     * @param array $data Dataset data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportDatasetToCSV($data, $metadata, $options)
    {
        $filename = $this->generateFilename('dataset', 'csv', $options);
        $filepath = $this->getExportPath($filename);
        
        $output = fopen($filepath, 'w');
        
        // Write metadata header
        fputcsv($output, ['Filtered Dataset Export']);
        fputcsv($output, ['Dataset Type', $metadata['dataset_type']]);
        fputcsv($output, ['Export Date', $metadata['export_date']]);
        fputcsv($output, ['Exported By', $metadata['user_name']]);
        fputcsv($output, ['Row Count', $metadata['row_count']]);
        
        // Write filters
        if (!empty($metadata['filters'])) {
            fputcsv($output, ['Filters Applied:']);
            foreach ($metadata['filters'] as $key => $value) {
                fputcsv($output, [$key, $value]);
            }
        }
        fputcsv($output, []); // Empty line
        
        // Write data
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($output, $headers);
            
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        
        fclose($output);
        return $filepath;
    }
    
    /**
     * Export dataset to Excel format
     * 
     * @param array $data Dataset data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportDatasetToExcel($data, $metadata, $options)
    {
        // For now, use CSV format as Excel
        $filename = $this->generateFilename('dataset', 'xlsx', $options);
        $filepath = $this->getExportPath($filename);
        
        $csvPath = $this->exportDatasetToCSV($data, $metadata, $options);
        copy($csvPath, $filepath);
        unlink($csvPath);
        
        return $filepath;
    }
    
    /**
     * Export dataset to PDF format
     * 
     * @param array $data Dataset data
     * @param array $metadata Metadata
     * @param array $options Options
     * @return string File path
     */
    private function exportDatasetToPDF($data, $metadata, $options)
    {
        $filename = $this->generateFilename('dataset', 'pdf', $options);
        $filepath = $this->getExportPath($filename);
        
        // Generate PDF content (simplified)
        $content = "WDB Analytics - Filtered Dataset Export\n\n";
        $content .= "Dataset Type: " . $metadata['dataset_type'] . "\n";
        $content .= "Export Date: " . $metadata['export_date'] . "\n";
        $content .= "Exported By: " . $metadata['user_name'] . "\n";
        $content .= "Row Count: " . $metadata['row_count'] . "\n\n";
        
        if (!empty($metadata['filters'])) {
            $content .= "Filters Applied:\n";
            foreach ($metadata['filters'] as $key => $value) {
                $content .= "  {$key}: {$value}\n";
            }
            $content .= "\n";
        }
        
        if (!empty($data)) {
            $headers = array_keys($data[0]);
            $content .= implode("\t", $headers) . "\n";
            $content .= str_repeat("-", 80) . "\n";
            
            $rowCount = 0;
            foreach ($data as $row) {
                $content .= implode("\t", $row) . "\n";
                $rowCount++;
                if ($rowCount >= 100) {
                    $content .= "\n... (showing first 100 rows)\n";
                    break;
                }
            }
        }
        
        file_put_contents($filepath, $content);
        return $filepath;
    }
    
    /**
     * Generate filename for export
     * 
     * @param string $type Export type
     * @param string $extension File extension
     * @param array $options Options
     * @return string Filename
     */
    private function generateFilename($type, $extension, $options)
    {
        $prefix = $options['filename_prefix'] ?? 'wdb-analytics';
        $timestamp = date('Y-m-d-His');
        return "{$prefix}-{$type}-{$timestamp}.{$extension}";
    }
    
    /**
     * Get export directory path
     * 
     * @param string $filename Filename
     * @return string Full file path
     */
    private function getExportPath($filename)
    {
        $exportDir = __DIR__ . '/../exports';
        
        // Ensure exports directory exists
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        return $exportDir . '/' . $filename;
    }
    
    // Chart data retrieval methods
    
    private function getMemberRegistrationTrend($options)
    {
        $filters = $options['filters'] ?? [];
        $trendData = $this->analyticsService->generateTrendAnalysis('member_registration', array_merge($filters, [
            'period' => 'monthly',
            'start_date' => $filters['start_date'] ?? date('Y-m-d', strtotime('-12 months')),
            'end_date' => $filters['end_date'] ?? date('Y-m-d')
        ]));
        
        return [
            'type' => 'line',
            'labels' => array_column($trendData, 'period'),
            'datasets' => [
                [
                    'label' => 'New Members',
                    'data' => array_column($trendData, 'count')
                ]
            ]
        ];
    }
    
    private function getGenderDistribution($options)
    {
        $filters = $options['filters'] ?? [];
        $analytics = $this->analyticsService->getMemberAnalytics($filters);
        
        $genderCounts = [];
        foreach ($analytics as $member) {
            $gender = $member['gender'] ?? 'Unknown';
            $genderCounts[$gender] = ($genderCounts[$gender] ?? 0) + 1;
        }
        
        return [
            'type' => 'pie',
            'labels' => array_keys($genderCounts),
            'datasets' => [
                [
                    'label' => 'Gender Distribution',
                    'data' => array_values($genderCounts)
                ]
            ]
        ];
    }
    
    private function getAgeDistribution($options)
    {
        $filters = $options['filters'] ?? [];
        $analytics = $this->analyticsService->getMemberAnalytics($filters);
        
        $ageCounts = [];
        foreach ($analytics as $member) {
            $ageGroup = $member['age_group'] ?? 'Unknown';
            $ageCounts[$ageGroup] = ($ageCounts[$ageGroup] ?? 0) + 1;
        }
        
        return [
            'type' => 'bar',
            'labels' => array_keys($ageCounts),
            'datasets' => [
                [
                    'label' => 'Age Distribution',
                    'data' => array_values($ageCounts)
                ]
            ]
        ];
    }
    
    private function getContributionTrend($options)
    {
        $filters = $options['filters'] ?? [];
        $trendData = $this->analyticsService->generateTrendAnalysis('contribution', array_merge($filters, [
            'period' => 'monthly',
            'start_date' => $filters['start_date'] ?? date('Y-m-d', strtotime('-12 months')),
            'end_date' => $filters['end_date'] ?? date('Y-m-d')
        ]));
        
        return [
            'type' => 'line',
            'labels' => array_column($trendData, 'period'),
            'datasets' => [
                [
                    'label' => 'Total Contributions',
                    'data' => array_column($trendData, 'total_amount')
                ]
            ]
        ];
    }
    
    private function getPaymentStatusDistribution($options)
    {
        $filters = $options['filters'] ?? [];
        $analytics = $this->analyticsService->getContributionAnalytics($filters);
        
        $statusCounts = [];
        foreach ($analytics as $contribution) {
            $status = $contribution['payment_status'] ?? 'Unknown';
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        
        return [
            'type' => 'pie',
            'labels' => array_keys($statusCounts),
            'datasets' => [
                [
                    'label' => 'Payment Status',
                    'data' => array_values($statusCounts)
                ]
            ]
        ];
    }
    
    private function getCenterPerformance($options)
    {
        $filters = $options['filters'] ?? [];
        $analytics = $this->analyticsService->getCenterAnalytics($filters);
        
        return [
            'type' => 'bar',
            'labels' => array_column($analytics, 'center_name'),
            'datasets' => [
                [
                    'label' => 'Total Members',
                    'data' => array_column($analytics, 'total_members')
                ],
                [
                    'label' => 'Total Revenue',
                    'data' => array_column($analytics, 'total_revenue')
                ]
            ]
        ];
    }
}
