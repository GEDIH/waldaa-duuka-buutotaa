<?php
/**
 * Report Generator
 * Generates comprehensive reports with export capabilities (PDF, Excel, CSV)
 * Requirements: 4.1, 4.2, 4.3, 4.6, 4.7
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/AnalyticsService.php';
require_once __DIR__ . '/KPICalculator.php';

class ReportGenerator
{
    private $db;
    private $conn;
    private $analyticsService;
    private $kpiCalculator;
    
    // Report templates
    private $templates = [
        'membership_summary' => 'Membership Summary Report',
        'financial_report' => 'Financial Performance Report',
        'demographic_analysis' => 'Demographic Analysis Report',
        'center_performance' => 'Center Performance Review',
        'executive_summary' => 'Executive Summary Report',
        'contribution_analysis' => 'Contribution Analysis Report',
        'growth_trends' => 'Growth Trends Report',
        'engagement_metrics' => 'Member Engagement Report'
    ];
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->analyticsService = new AnalyticsService();
        $this->kpiCalculator = new KPICalculator();
    }
    
    /**
     * Generate report based on template and parameters
     * 
     * @param string $template Report template name
     * @param array $parameters Report parameters (filters, date ranges, etc.)
     * @return array Report data structure
     */
    public function generateReport($template, $parameters = [])
    {
        // Validate template
        if (!isset($this->templates[$template])) {
            throw new Exception("Invalid report template: {$template}");
        }
        
        // Create report structure
        $report = [
            'id' => $this->generateReportId(),
            'title' => $this->templates[$template],
            'template' => $template,
            'parameters' => $parameters,
            'data' => [],
            'charts' => [],
            'metadata' => [
                'generated_at' => date('Y-m-d H:i:s'),
                'generated_by' => $parameters['user_id'] ?? 'system',
                'filters_applied' => $this->formatFilters($parameters),
                'date_range' => $this->getDateRange($parameters),
                'version' => '1.0'
            ],
            'summary' => []
        ];
        
        // Generate report data based on template
        switch ($template) {
            case 'membership_summary':
                $report['data'] = $this->generateMembershipSummary($parameters);
                $report['charts'] = $this->getMembershipCharts($parameters);
                break;
                
            case 'financial_report':
                $report['data'] = $this->generateFinancialReport($parameters);
                $report['charts'] = $this->getFinancialCharts($parameters);
                break;
                
            case 'demographic_analysis':
                $report['data'] = $this->generateDemographicAnalysis($parameters);
                $report['charts'] = $this->getDemographicCharts($parameters);
                break;
                
            case 'center_performance':
                $report['data'] = $this->generateCenterPerformance($parameters);
                $report['charts'] = $this->getCenterCharts($parameters);
                break;
                
            case 'executive_summary':
                $report['data'] = $this->generateExecutiveSummary($parameters);
                $report['charts'] = $this->getExecutiveCharts($parameters);
                break;
                
            case 'contribution_analysis':
                $report['data'] = $this->generateContributionAnalysis($parameters);
                $report['charts'] = $this->getContributionCharts($parameters);
                break;
                
            case 'growth_trends':
                $report['data'] = $this->generateGrowthTrends($parameters);
                $report['charts'] = $this->getGrowthCharts($parameters);
                break;
                
            case 'engagement_metrics':
                $report['data'] = $this->generateEngagementMetrics($parameters);
                $report['charts'] = $this->getEngagementCharts($parameters);
                break;
                
            default:
                throw new Exception("Template not implemented: {$template}");
        }
        
        // Generate summary
        $report['summary'] = $this->generateReportSummary($report);
        
        return $report;
    }
    
    /**
     * Export report to PDF format
     * 
     * @param array $report Report data structure
     * @return string Path to generated PDF file
     */
    public function exportToPDF($report)
    {
        // For now, return a simple implementation
        // In production, this would use TCPDF or similar library
        
        $filename = $this->generateFilename($report, 'pdf');
        $filepath = __DIR__ . '/../exports/' . $filename;
        
        // Ensure exports directory exists
        $exportDir = __DIR__ . '/../exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Generate PDF content (simplified for now)
        $pdfContent = $this->generatePDFContent($report);
        
        // Save to file
        file_put_contents($filepath, $pdfContent);
        
        return $filepath;
    }
    
    /**
     * Export report to Excel format
     * 
     * @param array $report Report data structure
     * @return string Path to generated Excel file
     */
    public function exportToExcel($report)
    {
        // For now, return a simple CSV implementation
        // In production, this would use PhpSpreadsheet library
        
        $filename = $this->generateFilename($report, 'xlsx');
        $filepath = __DIR__ . '/../exports/' . $filename;
        
        // Ensure exports directory exists
        $exportDir = __DIR__ . '/../exports';
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }
        
        // Generate Excel content (simplified as CSV for now)
        $excelContent = $this->generateExcelContent($report);
        
        // Save to file
        file_put_contents($filepath, $excelContent);
        
        return $filepath;
    }
    
    /**
     * Export data to CSV format
     * 
     * @param array $data Data to export
     * @param array $options Export options
     * @return string CSV content
     */
    public function exportToCSV($data, $options = [])
    {
        if (empty($data)) {
            return '';
        }
        
        $delimiter = $options['delimiter'] ?? ',';
        $enclosure = $options['enclosure'] ?? '"';
        $includeHeaders = $options['include_headers'] ?? true;
        
        $output = fopen('php://temp', 'r+');
        
        // Write headers
        if ($includeHeaders && !empty($data)) {
            $headers = array_keys($data[0]);
            fputcsv($output, $headers, $delimiter, $enclosure);
        }
        
        // Write data rows
        foreach ($data as $row) {
            fputcsv($output, $row, $delimiter, $enclosure);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Schedule report for automated generation
     * 
     * @param array $config Schedule configuration
     * @return array Schedule details
     */
    public function scheduleReport($config)
    {
        // Validate configuration
        $required = ['template', 'frequency', 'recipients'];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate template
        if (!isset($this->templates[$config['template']])) {
            throw new Exception("Invalid report template: {$config['template']}");
        }
        
        // Validate frequency
        $validFrequencies = ['daily', 'weekly', 'monthly'];
        if (!in_array($config['frequency'], $validFrequencies)) {
            throw new Exception("Invalid frequency. Must be: daily, weekly, or monthly");
        }
        
        // Validate recipients
        if (!is_array($config['recipients']) || empty($config['recipients'])) {
            throw new Exception("Recipients must be a non-empty array of email addresses");
        }
        
        foreach ($config['recipients'] as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email address: {$email}");
            }
        }
        
        // Create schedule record
        $scheduleId = $this->createScheduleRecord($config);
        $nextRun = $this->calculateNextRun($config['frequency'], $config['time'] ?? '09:00');
        
        // Update next_run in database
        $this->updateNextRun($scheduleId, $nextRun);
        
        return [
            'schedule_id' => $scheduleId,
            'template' => $config['template'],
            'frequency' => $config['frequency'],
            'next_run' => $nextRun,
            'recipients' => $config['recipients'],
            'status' => 'active',
            'parameters' => $config['parameters'] ?? []
        ];
    }
    
    /**
     * Get scheduled reports
     * 
     * @param array $filters Optional filters (status, template)
     * @return array List of scheduled reports
     */
    public function getScheduledReports($filters = [])
    {
        try {
            $sql = "SELECT * FROM report_schedules WHERE 1=1";
            $params = [];
            
            if (isset($filters['status'])) {
                $sql .= " AND status = :status";
                $params[':status'] = $filters['status'];
            }
            
            if (isset($filters['template'])) {
                $sql .= " AND template = :template";
                $params[':template'] = $filters['template'];
            }
            
            $sql .= " ORDER BY next_run ASC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($schedules as &$schedule) {
                $schedule['recipients'] = json_decode($schedule['recipients'], true);
                $schedule['parameters'] = json_decode($schedule['parameters'], true);
            }
            
            return $schedules;
            
        } catch (Exception $e) {
            error_log("Failed to get scheduled reports: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update scheduled report
     * 
     * @param int $scheduleId Schedule ID
     * @param array $updates Fields to update
     * @return bool Success status
     */
    public function updateScheduledReport($scheduleId, $updates)
    {
        try {
            $allowedFields = ['frequency', 'recipients', 'parameters', 'status'];
            $setClauses = [];
            $params = [':id' => $scheduleId];
            
            foreach ($updates as $field => $value) {
                if (in_array($field, $allowedFields)) {
                    $setClauses[] = "{$field} = :{$field}";
                    
                    if ($field === 'recipients' || $field === 'parameters') {
                        $params[":{$field}"] = json_encode($value);
                    } else {
                        $params[":{$field}"] = $value;
                    }
                }
            }
            
            if (empty($setClauses)) {
                return false;
            }
            
            $sql = "UPDATE report_schedules SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $this->conn->prepare($sql);
            
            return $stmt->execute($params);
            
        } catch (Exception $e) {
            error_log("Failed to update scheduled report: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete scheduled report
     * 
     * @param int $scheduleId Schedule ID
     * @return bool Success status
     */
    public function deleteScheduledReport($scheduleId)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE report_schedules SET status = 'deleted' WHERE id = :id");
            return $stmt->execute([':id' => $scheduleId]);
        } catch (Exception $e) {
            error_log("Failed to delete scheduled report: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get due scheduled reports
     * 
     * @return array List of reports due for generation
     */
    public function getDueScheduledReports()
    {
        try {
            $stmt = $this->conn->prepare("
                SELECT * FROM report_schedules 
                WHERE status = 'active' 
                AND (next_run IS NULL OR next_run <= NOW())
                ORDER BY next_run ASC
            ");
            
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decode JSON fields
            foreach ($schedules as &$schedule) {
                $schedule['recipients'] = json_decode($schedule['recipients'], true);
                $schedule['parameters'] = json_decode($schedule['parameters'], true);
            }
            
            return $schedules;
            
        } catch (Exception $e) {
            error_log("Failed to get due scheduled reports: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Process scheduled report
     * 
     * @param array $schedule Schedule configuration
     * @return array Processing result
     */
    public function processScheduledReport($schedule)
    {
        $result = [
            'schedule_id' => $schedule['id'],
            'success' => false,
            'report_id' => null,
            'error' => null,
            'sent_to' => []
        ];
        
        try {
            // Generate report
            $report = $this->generateReport($schedule['template'], $schedule['parameters'] ?? []);
            $result['report_id'] = $report['id'];
            
            // Export report to PDF
            $pdfPath = $this->exportToPDF($report);
            
            // Send email to recipients
            foreach ($schedule['recipients'] as $recipient) {
                try {
                    $this->sendReportEmail($recipient, $report, $pdfPath);
                    $result['sent_to'][] = $recipient;
                } catch (Exception $e) {
                    error_log("Failed to send report to {$recipient}: " . $e->getMessage());
                }
            }
            
            // Update schedule record
            $this->updateScheduleAfterRun($schedule['id'], $schedule['frequency'], true);
            
            $result['success'] = !empty($result['sent_to']);
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            error_log("Failed to process scheduled report {$schedule['id']}: " . $e->getMessage());
            
            // Update schedule record with failure
            $this->updateScheduleAfterRun($schedule['id'], $schedule['frequency'], false);
        }
        
        return $result;
    }
    
    /**
     * Send report via email
     * 
     * @param string $recipient Email address
     * @param array $report Report data
     * @param string $attachmentPath Path to PDF attachment
     * @return bool Success status
     */
    private function sendReportEmail($recipient, $report, $attachmentPath)
    {
        // Email configuration
        $subject = "WDB Analytics Report: {$report['title']}";
        
        // Build email body
        $body = $this->buildReportEmailBody($report);
        
        // Email headers
        $headers = [
            'From: WDB Analytics <noreply@wdb.org>',
            'Reply-To: support@wdb.org',
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0'
        ];
        
        // Check if attachment exists
        if (file_exists($attachmentPath)) {
            // Send email with attachment
            return $this->sendEmailWithAttachment($recipient, $subject, $body, $attachmentPath, $headers);
        } else {
            // Send email without attachment
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            return mail($recipient, $subject, $body, implode("\r\n", $headers));
        }
    }
    
    /**
     * Send email with attachment
     * 
     * @param string $to Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string $attachmentPath Path to attachment
     * @param array $headers Email headers
     * @return bool Success status
     */
    private function sendEmailWithAttachment($to, $subject, $body, $attachmentPath, $headers)
    {
        // Generate boundary
        $boundary = md5(time());
        
        // Add boundary to headers
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";
        
        // Build message
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";
        
        // Add attachment
        if (file_exists($attachmentPath)) {
            $filename = basename($attachmentPath);
            $content = chunk_split(base64_encode(file_get_contents($attachmentPath)));
            
            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: application/pdf; name=\"{$filename}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$filename}\"\r\n\r\n";
            $message .= $content . "\r\n\r\n";
        }
        
        $message .= "--{$boundary}--";
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Build report email body
     * 
     * @param array $report Report data
     * @return string HTML email body
     */
    private function buildReportEmailBody($report)
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #2c3e50; color: white; padding: 20px; text-align: center; }
        .content { background: #f9f9f9; padding: 20px; margin: 20px 0; }
        .footer { text-align: center; color: #666; font-size: 12px; padding: 20px; }
        .key-finding { background: white; padding: 10px; margin: 10px 0; border-left: 4px solid #3498db; }
        .metadata { font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>WDB Analytics Report</h1>
            <p>' . htmlspecialchars($report['title']) . '</p>
        </div>
        
        <div class="content">
            <h2>Report Summary</h2>
            <p>Your scheduled analytics report has been generated and is attached to this email.</p>
            
            <h3>Key Findings:</h3>';
        
        foreach ($report['summary']['key_findings'] ?? [] as $finding) {
            $html .= '<div class="key-finding">' . htmlspecialchars($finding) . '</div>';
        }
        
        $html .= '
            <div class="metadata">
                <p><strong>Report ID:</strong> ' . htmlspecialchars($report['id']) . '</p>
                <p><strong>Generated:</strong> ' . htmlspecialchars($report['metadata']['generated_at']) . '</p>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated report from WDB Analytics System</p>
            <p>For questions or support, please contact support@wdb.org</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Update schedule after run
     * 
     * @param int $scheduleId Schedule ID
     * @param string $frequency Frequency
     * @param bool $success Whether the run was successful
     * @return bool Success status
     */
    private function updateScheduleAfterRun($scheduleId, $frequency, $success)
    {
        try {
            $nextRun = $this->calculateNextRun($frequency);
            
            $stmt = $this->conn->prepare("
                UPDATE report_schedules 
                SET last_run = NOW(), next_run = :next_run
                WHERE id = :id
            ");
            
            return $stmt->execute([
                ':id' => $scheduleId,
                ':next_run' => $nextRun
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to update schedule after run: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update next run time
     * 
     * @param int $scheduleId Schedule ID
     * @param string $nextRun Next run datetime
     * @return bool Success status
     */
    private function updateNextRun($scheduleId, $nextRun)
    {
        try {
            $stmt = $this->conn->prepare("UPDATE report_schedules SET next_run = :next_run WHERE id = :id");
            return $stmt->execute([':id' => $scheduleId, ':next_run' => $nextRun]);
        } catch (Exception $e) {
            error_log("Failed to update next run: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get available report templates
     * 
     * @return array List of available templates
     */
    public function getAvailableTemplates()
    {
        return $this->templates;
    }
    
    /**
     * Generate unique report ID
     * 
     * @return string Report ID
     */
    private function generateReportId()
    {
        return 'RPT-' . date('Ymd') . '-' . uniqid();
    }
    
    /**
     * Generate filename for export
     * 
     * @param array $report Report data
     * @param string $extension File extension
     * @return string Filename
     */
    private function generateFilename($report, $extension)
    {
        $template = str_replace('_', '-', $report['template']);
        $date = date('Y-m-d-His');
        return "{$template}-{$date}.{$extension}";
    }
    
    /**
     * Format filters for metadata
     * 
     * @param array $parameters Report parameters
     * @return array Formatted filters
     */
    private function formatFilters($parameters)
    {
        $filters = [];
        
        if (isset($parameters['center_id'])) {
            $filters['center'] = $this->getCenterName($parameters['center_id']);
        }
        
        if (isset($parameters['region'])) {
            $filters['region'] = $parameters['region'];
        }
        
        if (isset($parameters['status'])) {
            $filters['status'] = $parameters['status'];
        }
        
        return $filters;
    }
    
    /**
     * Get date range from parameters
     * 
     * @param array $parameters Report parameters
     * @return array Date range
     */
    private function getDateRange($parameters)
    {
        return [
            'start_date' => $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days')),
            'end_date' => $parameters['end_date'] ?? date('Y-m-d')
        ];
    }
    
    /**
     * Generate membership summary report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateMembershipSummary($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        
        return [
            'kpis' => $this->kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate),
            'members' => $this->analyticsService->getMemberAnalytics(['center_id' => $centerId]),
            'trends' => $this->analyticsService->generateTrendAnalysis('member_registration', [
                'period' => 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'center_id' => $centerId
            ])
        ];
    }
    
    /**
     * Generate financial report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateFinancialReport($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        
        return [
            'kpis' => $this->kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate),
            'contributions' => $this->analyticsService->getContributionAnalytics([
                'center_id' => $centerId,
                'date_from' => $startDate,
                'date_to' => $endDate
            ]),
            'trends' => $this->analyticsService->generateTrendAnalysis('contribution', [
                'period' => 'monthly',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'center_id' => $centerId
            ])
        ];
    }
    
    /**
     * Generate demographic analysis report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateDemographicAnalysis($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        
        $kpis = $this->kpiCalculator->calculateMembershipKPIs($centerId);
        
        return [
            'gender_distribution' => $kpis['gender_distribution'] ?? [],
            'age_distribution' => $kpis['age_distribution'] ?? [],
            'education_distribution' => $kpis['education_distribution'] ?? [],
            'total_members' => $kpis['total_members'] ?? 0
        ];
    }
    
    /**
     * Generate center performance report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateCenterPerformance($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        
        return [
            'centers' => $this->kpiCalculator->calculateCenterKPIs($centerId),
            'analytics' => $this->analyticsService->getCenterAnalytics(['center_id' => $centerId])
        ];
    }
    
    /**
     * Generate executive summary report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateExecutiveSummary($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        
        return [
            'membership' => $this->kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate),
            'financial' => $this->kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate),
            'growth' => $this->kpiCalculator->calculateGrowthKPIs($centerId),
            'engagement' => $this->kpiCalculator->calculateEngagementKPIs($centerId),
            'key_insights' => $this->generateKeyInsights($centerId, $startDate, $endDate)
        ];
    }
    
    /**
     * Generate contribution analysis report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateContributionAnalysis($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        $startDate = $parameters['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $parameters['end_date'] ?? date('Y-m-d');
        
        return [
            'kpis' => $this->kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate),
            'contributions' => $this->analyticsService->getContributionAnalytics([
                'center_id' => $centerId,
                'date_from' => $startDate,
                'date_to' => $endDate
            ])
        ];
    }
    
    /**
     * Generate growth trends report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateGrowthTrends($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        
        return [
            'growth_kpis' => $this->kpiCalculator->calculateGrowthKPIs($centerId),
            'member_trends' => $this->analyticsService->generateTrendAnalysis('member_registration', [
                'period' => 'monthly',
                'start_date' => date('Y-m-d', strtotime('-12 months')),
                'end_date' => date('Y-m-d'),
                'center_id' => $centerId
            ]),
            'revenue_trends' => $this->analyticsService->generateTrendAnalysis('contribution', [
                'period' => 'monthly',
                'start_date' => date('Y-m-d', strtotime('-12 months')),
                'end_date' => date('Y-m-d'),
                'center_id' => $centerId
            ])
        ];
    }
    
    /**
     * Generate engagement metrics report data
     * 
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function generateEngagementMetrics($parameters)
    {
        $centerId = $parameters['center_id'] ?? null;
        
        return [
            'engagement_kpis' => $this->kpiCalculator->calculateEngagementKPIs($centerId),
            'member_activity' => $this->getMemberActivityData($centerId)
        ];
    }
    
    /**
     * Get membership charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getMembershipCharts($parameters)
    {
        return [
            [
                'type' => 'line',
                'title' => 'Member Registration Trend',
                'data_key' => 'trends'
            ],
            [
                'type' => 'pie',
                'title' => 'Gender Distribution',
                'data_key' => 'kpis.gender_distribution'
            ],
            [
                'type' => 'bar',
                'title' => 'Payment Status',
                'data_key' => 'kpis.payment_status'
            ]
        ];
    }
    
    /**
     * Get financial charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getFinancialCharts($parameters)
    {
        return [
            [
                'type' => 'line',
                'title' => 'Revenue Trend',
                'data_key' => 'trends'
            ],
            [
                'type' => 'pie',
                'title' => 'Payment Method Distribution',
                'data_key' => 'kpis.payment_method_distribution'
            ],
            [
                'type' => 'bar',
                'title' => 'Monthly Revenue',
                'data_key' => 'trends'
            ]
        ];
    }
    
    /**
     * Get demographic charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getDemographicCharts($parameters)
    {
        return [
            [
                'type' => 'pie',
                'title' => 'Gender Distribution',
                'data_key' => 'gender_distribution'
            ],
            [
                'type' => 'bar',
                'title' => 'Age Distribution',
                'data_key' => 'age_distribution'
            ],
            [
                'type' => 'bar',
                'title' => 'Education Level Distribution',
                'data_key' => 'education_distribution'
            ]
        ];
    }
    
    /**
     * Get center charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getCenterCharts($parameters)
    {
        return [
            [
                'type' => 'bar',
                'title' => 'Center Performance Comparison',
                'data_key' => 'centers'
            ],
            [
                'type' => 'line',
                'title' => 'Center Growth Trend',
                'data_key' => 'analytics'
            ]
        ];
    }
    
    /**
     * Get executive charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getExecutiveCharts($parameters)
    {
        return [
            [
                'type' => 'line',
                'title' => 'Overall Growth Trend',
                'data_key' => 'growth'
            ],
            [
                'type' => 'bar',
                'title' => 'Key Performance Indicators',
                'data_key' => 'membership'
            ]
        ];
    }
    
    /**
     * Get contribution charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getContributionCharts($parameters)
    {
        return [
            [
                'type' => 'line',
                'title' => 'Contribution Trend',
                'data_key' => 'contributions'
            ],
            [
                'type' => 'pie',
                'title' => 'Payment Status Distribution',
                'data_key' => 'kpis'
            ]
        ];
    }
    
    /**
     * Get growth charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getGrowthCharts($parameters)
    {
        return [
            [
                'type' => 'line',
                'title' => 'Member Growth Trend',
                'data_key' => 'member_trends'
            ],
            [
                'type' => 'line',
                'title' => 'Revenue Growth Trend',
                'data_key' => 'revenue_trends'
            ]
        ];
    }
    
    /**
     * Get engagement charts configuration
     * 
     * @param array $parameters Report parameters
     * @return array Charts configuration
     */
    private function getEngagementCharts($parameters)
    {
        return [
            [
                'type' => 'bar',
                'title' => 'Member Activity Levels',
                'data_key' => 'engagement_kpis'
            ],
            [
                'type' => 'line',
                'title' => 'Engagement Trend',
                'data_key' => 'member_activity'
            ]
        ];
    }
    
    /**
     * Generate report summary
     * 
     * @param array $report Report data
     * @return array Summary data
     */
    private function generateReportSummary($report)
    {
        $summary = [
            'report_id' => $report['id'],
            'title' => $report['title'],
            'generated_at' => $report['metadata']['generated_at'],
            'key_findings' => []
        ];
        
        // Add template-specific key findings
        if (isset($report['data']['kpis'])) {
            $kpis = $report['data']['kpis'];
            
            if (isset($kpis['total_members'])) {
                $summary['key_findings'][] = "Total Members: " . $kpis['total_members'];
            }
            
            if (isset($kpis['total_revenue'])) {
                $summary['key_findings'][] = "Total Revenue: " . number_format($kpis['total_revenue'], 2);
            }
            
            if (isset($kpis['payment_compliance_rate'])) {
                $summary['key_findings'][] = "Payment Compliance: " . $kpis['payment_compliance_rate'] . "%";
            }
        }
        
        return $summary;
    }
    
    /**
     * Generate key insights for executive summary
     * 
     * @param int|null $centerId Center ID
     * @param string $startDate Start date
     * @param string $endDate End date
     * @return array Key insights
     */
    private function generateKeyInsights($centerId, $startDate, $endDate)
    {
        $insights = [];
        
        $membershipKPIs = $this->kpiCalculator->calculateMembershipKPIs($centerId, $startDate, $endDate);
        $financialKPIs = $this->kpiCalculator->calculateFinancialKPIs($centerId, $startDate, $endDate);
        $growthKPIs = $this->kpiCalculator->calculateGrowthKPIs($centerId);
        
        // Membership insights
        if (isset($membershipKPIs['payment_compliance_rate'])) {
            $rate = $membershipKPIs['payment_compliance_rate'];
            $insights[] = [
                'category' => 'Membership',
                'insight' => "Payment compliance rate is {$rate}%",
                'status' => $rate >= 80 ? 'positive' : ($rate >= 60 ? 'neutral' : 'negative')
            ];
        }
        
        // Financial insights
        if (isset($financialKPIs['collection_efficiency'])) {
            $efficiency = $financialKPIs['collection_efficiency'];
            $insights[] = [
                'category' => 'Financial',
                'insight' => "Collection efficiency is {$efficiency}%",
                'status' => $efficiency >= 75 ? 'positive' : ($efficiency >= 50 ? 'neutral' : 'negative')
            ];
        }
        
        // Growth insights
        if (isset($growthKPIs['member_growth_percentage'])) {
            $growth = $growthKPIs['member_growth_percentage'];
            $insights[] = [
                'category' => 'Growth',
                'insight' => "Member growth is {$growth}%",
                'status' => $growth > 5 ? 'positive' : ($growth > 0 ? 'neutral' : 'negative')
            ];
        }
        
        return $insights;
    }
    
    /**
     * Generate PDF content (simplified)
     * 
     * @param array $report Report data
     * @return string PDF content
     */
    private function generatePDFContent($report)
    {
        // Simplified PDF generation - in production use TCPDF
        $content = "WDB Analytics Report\n\n";
        $content .= "Title: " . $report['title'] . "\n";
        $content .= "Generated: " . $report['metadata']['generated_at'] . "\n";
        $content .= "Report ID: " . $report['id'] . "\n\n";
        
        $content .= "Summary:\n";
        foreach ($report['summary']['key_findings'] ?? [] as $finding) {
            $content .= "- " . $finding . "\n";
        }
        
        return $content;
    }
    
    /**
     * Generate Excel content (simplified as CSV)
     * 
     * @param array $report Report data
     * @return string Excel content
     */
    private function generateExcelContent($report)
    {
        // Simplified Excel generation - in production use PhpSpreadsheet
        $content = "WDB Analytics Report\n";
        $content .= "Title," . $report['title'] . "\n";
        $content .= "Generated," . $report['metadata']['generated_at'] . "\n";
        $content .= "Report ID," . $report['id'] . "\n\n";
        
        // Add data if available
        if (isset($report['data']['kpis'])) {
            $content .= "\nKey Performance Indicators\n";
            foreach ($report['data']['kpis'] as $key => $value) {
                if (!is_array($value)) {
                    $content .= $key . "," . $value . "\n";
                }
            }
        }
        
        return $content;
    }
    
    /**
     * Get center name by ID
     * 
     * @param int $centerId Center ID
     * @return string Center name
     */
    private function getCenterName($centerId)
    {
        try {
            $stmt = $this->conn->prepare("SELECT name FROM centers WHERE id = :id");
            $stmt->execute([':id' => $centerId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['name'] ?? 'Unknown Center';
        } catch (Exception $e) {
            return 'Unknown Center';
        }
    }
    
    /**
     * Get member activity data
     * 
     * @param int|null $centerId Center ID
     * @return array Activity data
     */
    private function getMemberActivityData($centerId)
    {
        // Placeholder for member activity data
        return [
            'active_7d' => 0,
            'active_30d' => 0,
            'active_90d' => 0
        ];
    }
    
    /**
     * Create schedule record in database
     * 
     * @param array $config Schedule configuration
     * @return int Schedule ID
     */
    private function createScheduleRecord($config)
    {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO report_schedules 
                (template, frequency, recipients, parameters, status, created_at)
                VALUES (:template, :frequency, :recipients, :parameters, 'active', NOW())
            ");
            
            $stmt->execute([
                ':template' => $config['template'],
                ':frequency' => $config['frequency'],
                ':recipients' => json_encode($config['recipients']),
                ':parameters' => json_encode($config['parameters'] ?? [])
            ]);
            
            return (int)$this->conn->lastInsertId();
            
        } catch (Exception $e) {
            error_log("Failed to create schedule record: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Calculate next run time based on frequency
     * 
     * @param string $frequency Frequency (daily, weekly, monthly)
     * @param string $time Time of day (HH:MM format, default 09:00)
     * @return string Next run datetime
     */
    private function calculateNextRun($frequency, $time = '09:00')
    {
        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time = '09:00';
        }
        
        $now = new DateTime();
        $nextRun = new DateTime();
        
        // Set the time
        list($hour, $minute) = explode(':', $time);
        $nextRun->setTime((int)$hour, (int)$minute, 0);
        
        // If the time has already passed today, start from tomorrow
        if ($nextRun <= $now) {
            $nextRun->modify('+1 day');
        }
        
        // Adjust based on frequency
        switch ($frequency) {
            case 'daily':
                // Already set to next occurrence
                break;
                
            case 'weekly':
                // Set to next Monday at specified time
                if ($nextRun->format('N') != 1) {
                    $nextRun->modify('next monday');
                }
                break;
                
            case 'monthly':
                // Set to first day of next month at specified time
                $nextRun->modify('first day of next month');
                $nextRun->setTime((int)$hour, (int)$minute, 0);
                break;
                
            default:
                // Default to daily
                break;
        }
        
        return $nextRun->format('Y-m-d H:i:s');
    }
}
