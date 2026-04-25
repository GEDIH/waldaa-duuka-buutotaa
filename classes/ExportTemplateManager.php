<?php
/**
 * Export Template Manager
 * Manages custom export templates for recurring reports
 * Allows administrators to define specific data formats and layouts
 * Requirements: 8.7
 */

require_once __DIR__ . '/../api/config/database.php';

class ExportTemplateManager
{
    private $db;
    private $conn;
    
    // Template types
    const TYPE_CHART = 'chart';
    const TYPE_TABLE = 'table';
    const TYPE_DATASET = 'dataset';
    const TYPE_REPORT = 'report';
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }
    
    /**
     * Create a new export template
     * 
     * @param array $templateData Template configuration
     * @return array Created template with ID
     */
    public function createTemplate($templateData)
    {
        // Validate required fields
        $requiredFields = ['name', 'type', 'format', 'configuration'];
        foreach ($requiredFields as $field) {
            if (!isset($templateData[$field])) {
                throw new Exception("Missing required field: {$field}");
            }
        }
        
        // Validate template type
        $validTypes = [self::TYPE_CHART, self::TYPE_TABLE, self::TYPE_DATASET, self::TYPE_REPORT];
        if (!in_array($templateData['type'], $validTypes)) {
            throw new Exception("Invalid template type: {$templateData['type']}");
        }
        
        // Generate template ID
        $templateId = $this->generateTemplateId();
        
        // Prepare template data
        $template = [
            'id' => $templateId,
            'name' => $templateData['name'],
            'description' => $templateData['description'] ?? '',
            'type' => $templateData['type'],
            'format' => $templateData['format'],
            'configuration' => json_encode($templateData['configuration']),
            'created_by' => $templateData['created_by'] ?? 'system',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'is_active' => 1
        ];
        
        // Store template in database
        $this->saveTemplateToDatabase($template);
        
        // Return template with decoded configuration
        $template['configuration'] = $templateData['configuration'];
        
        return [
            'success' => true,
            'template' => $template,
            'message' => 'Template created successfully'
        ];
    }
    
    /**
     * Update an existing export template
     * 
     * @param string $templateId Template ID
     * @param array $templateData Updated template data
     * @return array Updated template
     */
    public function updateTemplate($templateId, $templateData)
    {
        // Check if template exists
        $existingTemplate = $this->getTemplate($templateId);
        if (!$existingTemplate) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        // Prepare update data
        $updateData = [];
        
        if (isset($templateData['name'])) {
            $updateData['name'] = $templateData['name'];
        }
        
        if (isset($templateData['description'])) {
            $updateData['description'] = $templateData['description'];
        }
        
        if (isset($templateData['configuration'])) {
            $updateData['configuration'] = json_encode($templateData['configuration']);
        }
        
        if (isset($templateData['is_active'])) {
            $updateData['is_active'] = $templateData['is_active'] ? 1 : 0;
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        $updateData['updated_by'] = $templateData['updated_by'] ?? 'system';
        
        // Update template in database
        $this->updateTemplateInDatabase($templateId, $updateData);
        
        // Return updated template
        return [
            'success' => true,
            'template' => $this->getTemplate($templateId),
            'message' => 'Template updated successfully'
        ];
    }
    
    /**
     * Get a template by ID
     * 
     * @param string $templateId Template ID
     * @return array|null Template data or null if not found
     */
    public function getTemplate($templateId)
    {
        $template = $this->getTemplateFromDatabase($templateId);
        
        if ($template && isset($template['configuration'])) {
            $template['configuration'] = json_decode($template['configuration'], true);
        }
        
        return $template;
    }
    
    /**
     * Get all templates
     * 
     * @param array $filters Optional filters (type, format, is_active)
     * @return array List of templates
     */
    public function getTemplates($filters = [])
    {
        $templates = $this->getTemplatesFromDatabase($filters);
        
        // Decode configuration for each template
        foreach ($templates as &$template) {
            if (isset($template['configuration'])) {
                $template['configuration'] = json_decode($template['configuration'], true);
            }
        }
        
        return $templates;
    }
    
    /**
     * Delete a template
     * 
     * @param string $templateId Template ID
     * @return array Result
     */
    public function deleteTemplate($templateId)
    {
        // Check if template exists
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        // Delete template from database
        $this->deleteTemplateFromDatabase($templateId);
        
        return [
            'success' => true,
            'message' => 'Template deleted successfully'
        ];
    }
    
    /**
     * Apply template to export data
     * 
     * @param string $templateId Template ID
     * @param array $data Data to export
     * @return array Formatted export data
     */
    public function applyTemplate($templateId, $data)
    {
        // Get template
        $template = $this->getTemplate($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        // Apply template configuration to data
        $formattedData = $this->formatDataWithTemplate($data, $template);
        
        return [
            'success' => true,
            'template_id' => $templateId,
            'template_name' => $template['name'],
            'formatted_data' => $formattedData,
            'metadata' => [
                'template_applied' => true,
                'template_type' => $template['type'],
                'format' => $template['format'],
                'applied_at' => date('Y-m-d H:i:s')
            ]
        ];
    }
    
    /**
     * Generate unique template ID
     * 
     * @return string Template ID
     */
    private function generateTemplateId()
    {
        return 'TPL-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
    }
    
    /**
     * Format data according to template configuration
     * 
     * @param array $data Raw data
     * @param array $template Template configuration
     * @return array Formatted data
     */
    private function formatDataWithTemplate($data, $template)
    {
        $config = $template['configuration'];
        $formattedData = [];
        
        // Apply column selection
        if (isset($config['columns']) && !empty($config['columns'])) {
            $formattedData = $this->selectColumns($data, $config['columns']);
        } else {
            $formattedData = $data;
        }
        
        // Apply column renaming
        if (isset($config['column_mapping']) && !empty($config['column_mapping'])) {
            $formattedData = $this->renameColumns($formattedData, $config['column_mapping']);
        }
        
        // Apply sorting
        if (isset($config['sort_by'])) {
            $formattedData = $this->sortData($formattedData, $config['sort_by'], $config['sort_order'] ?? 'asc');
        }
        
        // Apply filtering
        if (isset($config['filters']) && !empty($config['filters'])) {
            $formattedData = $this->filterData($formattedData, $config['filters']);
        }
        
        // Apply aggregation
        if (isset($config['aggregations']) && !empty($config['aggregations'])) {
            $formattedData = $this->aggregateData($formattedData, $config['aggregations']);
        }
        
        // Apply formatting rules
        if (isset($config['formatting']) && !empty($config['formatting'])) {
            $formattedData = $this->applyFormatting($formattedData, $config['formatting']);
        }
        
        return $formattedData;
    }
    
    /**
     * Select specific columns from data
     */
    private function selectColumns($data, $columns)
    {
        $result = [];
        foreach ($data as $row) {
            $newRow = [];
            foreach ($columns as $column) {
                if (isset($row[$column])) {
                    $newRow[$column] = $row[$column];
                }
            }
            $result[] = $newRow;
        }
        return $result;
    }
    
    /**
     * Rename columns according to mapping
     */
    private function renameColumns($data, $mapping)
    {
        $result = [];
        foreach ($data as $row) {
            $newRow = [];
            foreach ($row as $key => $value) {
                $newKey = $mapping[$key] ?? $key;
                $newRow[$newKey] = $value;
            }
            $result[] = $newRow;
        }
        return $result;
    }
    
    /**
     * Sort data by column
     */
    private function sortData($data, $sortBy, $sortOrder = 'asc')
    {
        usort($data, function($a, $b) use ($sortBy, $sortOrder) {
            $aVal = $a[$sortBy] ?? '';
            $bVal = $b[$sortBy] ?? '';
            
            if ($sortOrder === 'desc') {
                return $bVal <=> $aVal;
            }
            return $aVal <=> $bVal;
        });
        
        return $data;
    }
    
    /**
     * Filter data based on conditions
     */
    private function filterData($data, $filters)
    {
        return array_filter($data, function($row) use ($filters) {
            foreach ($filters as $filter) {
                $column = $filter['column'];
                $operator = $filter['operator'];
                $value = $filter['value'];
                
                if (!isset($row[$column])) {
                    return false;
                }
                
                $rowValue = $row[$column];
                
                switch ($operator) {
                    case '=':
                    case '==':
                        if ($rowValue != $value) return false;
                        break;
                    case '!=':
                        if ($rowValue == $value) return false;
                        break;
                    case '>':
                        if ($rowValue <= $value) return false;
                        break;
                    case '>=':
                        if ($rowValue < $value) return false;
                        break;
                    case '<':
                        if ($rowValue >= $value) return false;
                        break;
                    case '<=':
                        if ($rowValue > $value) return false;
                        break;
                    case 'contains':
                        if (strpos($rowValue, $value) === false) return false;
                        break;
                }
            }
            return true;
        });
    }
    
    /**
     * Aggregate data
     */
    private function aggregateData($data, $aggregations)
    {
        // Simple aggregation implementation
        $result = [];
        $groupBy = $aggregations['group_by'] ?? null;
        
        if ($groupBy) {
            $groups = [];
            foreach ($data as $row) {
                $groupKey = $row[$groupBy] ?? 'unknown';
                if (!isset($groups[$groupKey])) {
                    $groups[$groupKey] = [];
                }
                $groups[$groupKey][] = $row;
            }
            
            foreach ($groups as $groupKey => $groupData) {
                $aggregatedRow = [$groupBy => $groupKey];
                
                foreach ($aggregations['functions'] ?? [] as $func) {
                    $column = $func['column'];
                    $operation = $func['operation'];
                    $alias = $func['alias'] ?? "{$operation}_{$column}";
                    
                    $values = array_column($groupData, $column);
                    
                    switch ($operation) {
                        case 'sum':
                            $aggregatedRow[$alias] = array_sum($values);
                            break;
                        case 'avg':
                            $aggregatedRow[$alias] = count($values) > 0 ? array_sum($values) / count($values) : 0;
                            break;
                        case 'count':
                            $aggregatedRow[$alias] = count($values);
                            break;
                        case 'min':
                            $aggregatedRow[$alias] = count($values) > 0 ? min($values) : null;
                            break;
                        case 'max':
                            $aggregatedRow[$alias] = count($values) > 0 ? max($values) : null;
                            break;
                    }
                }
                
                $result[] = $aggregatedRow;
            }
        }
        
        return $result ?: $data;
    }
    
    /**
     * Apply formatting rules to data
     */
    private function applyFormatting($data, $formatting)
    {
        foreach ($data as &$row) {
            foreach ($formatting as $column => $format) {
                if (isset($row[$column])) {
                    $row[$column] = $this->formatValue($row[$column], $format);
                }
            }
        }
        return $data;
    }
    
    /**
     * Format a single value
     */
    private function formatValue($value, $format)
    {
        switch ($format['type']) {
            case 'number':
                $decimals = $format['decimals'] ?? 2;
                return number_format($value, $decimals);
            
            case 'currency':
                $decimals = $format['decimals'] ?? 2;
                $symbol = $format['symbol'] ?? '$';
                return $symbol . number_format($value, $decimals);
            
            case 'percentage':
                $decimals = $format['decimals'] ?? 1;
                return number_format($value, $decimals) . '%';
            
            case 'date':
                $dateFormat = $format['format'] ?? 'Y-m-d';
                return date($dateFormat, strtotime($value));
            
            case 'uppercase':
                return strtoupper($value);
            
            case 'lowercase':
                return strtolower($value);
            
            case 'capitalize':
                return ucwords($value);
            
            default:
                return $value;
        }
    }
    
    // Database operations (using file-based storage for simplicity)
    
    private function saveTemplateToDatabase($template)
    {
        $templatesDir = __DIR__ . '/../exports/templates';
        if (!is_dir($templatesDir)) {
            mkdir($templatesDir, 0755, true);
        }
        
        $filepath = $templatesDir . '/' . $template['id'] . '.json';
        file_put_contents($filepath, json_encode($template, JSON_PRETTY_PRINT));
    }
    
    private function getTemplateFromDatabase($templateId)
    {
        $filepath = __DIR__ . '/../exports/templates/' . $templateId . '.json';
        if (!file_exists($filepath)) {
            return null;
        }
        
        return json_decode(file_get_contents($filepath), true);
    }
    
    private function getTemplatesFromDatabase($filters = [])
    {
        $templatesDir = __DIR__ . '/../exports/templates';
        if (!is_dir($templatesDir)) {
            return [];
        }
        
        $templates = [];
        $files = glob($templatesDir . '/*.json');
        
        foreach ($files as $file) {
            $template = json_decode(file_get_contents($file), true);
            
            // Apply filters
            if (!empty($filters)) {
                if (isset($filters['type']) && $template['type'] !== $filters['type']) {
                    continue;
                }
                if (isset($filters['format']) && $template['format'] !== $filters['format']) {
                    continue;
                }
                if (isset($filters['is_active']) && $template['is_active'] != $filters['is_active']) {
                    continue;
                }
            }
            
            $templates[] = $template;
        }
        
        return $templates;
    }
    
    private function updateTemplateInDatabase($templateId, $updateData)
    {
        $template = $this->getTemplateFromDatabase($templateId);
        if (!$template) {
            throw new Exception("Template not found: {$templateId}");
        }
        
        foreach ($updateData as $key => $value) {
            $template[$key] = $value;
        }
        
        $this->saveTemplateToDatabase($template);
    }
    
    private function deleteTemplateFromDatabase($templateId)
    {
        $filepath = __DIR__ . '/../exports/templates/' . $templateId . '.json';
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }
}
