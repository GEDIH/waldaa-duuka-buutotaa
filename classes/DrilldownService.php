<?php
/**
 * DrilldownService - Enhanced KPI Drilldown Data Retrieval Service
 * 
 * Provides centralized data retrieval for KPI drilldown modals with advanced
 * filtering, sorting, pagination, security controls, and audit logging.
 * 
 * Requirements: 11.1, 11.4, 11.6
 */

require_once __DIR__ . '/../api/config/database.php';
require_once __DIR__ . '/SecurityManager.php';
require_once __DIR__ . '/AuditLogger.php';

class DrilldownService {
    private $db;
    private $securityManager;
    private $auditLogger;
    
    // Valid KPI types
    private const VALID_KPI_TYPES = [
        'total_members',
        'active_members',
        'paid_members',
        'unpaid_members',
        'new_today',
        'new_registrants',
        'total_admins',
        'all_centers'
    ];
    
    // Valid filter keys for member KPIs
    private const MEMBER_FILTER_KEYS = [
        'center_id',
        'region',
        'payment_status',
        'status',
        'date_from',
        'date_to',
        'gender'
    ];
    
    // Valid filter keys for admin KPIs
    private const ADMIN_FILTER_KEYS = [
        'center_id',
        'region',
        'role',
        'status'
    ];
    
    // Valid filter keys for center KPIs
    private const CENTER_FILTER_KEYS = [
        'region',
        'status'
    ];
    
    // Valid sort columns for members
    private const MEMBER_SORT_COLUMNS = [
        'member_id',
        'full_name',
        'email',
        'mobile_phone',
        'gender',
        'center_name',
        'region',
        'payment_status',
        'membership_date',
        'status',
        'created_at'
    ];
    
    // Valid sort columns for admins
    private const ADMIN_SORT_COLUMNS = [
        'id',
        'full_name',
        'email',
        'role',
        'center_name',
        'region',
        'status',
        'last_login',
        'created_at'
    ];
    
    // Valid sort columns for centers
    private const CENTER_SORT_COLUMNS = [
        'id',
        'name',
        'code',
        'address',
        'city',
        'region',
        'status',
        'total_members',
        'contact_person'
    ];
    
    // Sensitive fields to exclude from responses
    private const SENSITIVE_FIELDS = [
        'password',
        'password_hash',
        'payment_token',
        'reset_token',
        'verification_token'
    ];
    
    /**
     * Constructor
     */
    public function __construct() {
        $dbInstance = Database::getInstance();
        $this->db = $dbInstance->getConnection();
        
        // Set global connection for SecurityManager
        global $conn;
        $conn = $this->db;
        
        $this->securityManager = new SecurityManager();
        
        // Initialize AuditLogger with PDO connection
        $this->auditLogger = new AuditLogger($this->db);
    }
    
    /**
     * Main data retrieval method
     * 
     * Requirements: 11.1, 11.4, 11.6
     * 
     * @param string $kpiType KPI type identifier
     * @param array $filters Filter criteria
     * @param int $page Page number (1-indexed)
     * @param int $limit Records per page
     * @param string|null $sortBy Column to sort by
     * @param string $sortOrder Sort direction (ASC or DESC)
     * @return array Response with data, total, pagination info
     * @throws Exception
     */
    public function getDrilldownData(
        string $kpiType,
        array $filters = [],
        int $page = 1,
        int $limit = 50,
        ?string $sortBy = null,
        string $sortOrder = 'ASC'
    ): array {
        // Verify user has superadmin role (Requirement 11.1)
        $this->verifyAccess();
        
        // Validate KPI type
        if (!in_array($kpiType, self::VALID_KPI_TYPES)) {
            throw new Exception("Invalid KPI type: {$kpiType}");
        }
        
        // Validate and sanitize filters
        $filters = $this->validateFilters($filters, $kpiType);
        
        // Sanitize sort parameters
        $sortBy = $this->sanitizeSortColumn($sortBy, $kpiType);
        $sortOrder = strtoupper($sortOrder) === 'DESC' ? 'DESC' : 'ASC';
        
        // Validate pagination parameters
        $page = max(1, $page);
        $limit = min(max(1, $limit), 100); // Max 100 records per page
        $offset = ($page - 1) * $limit;
        
        // Log drilldown access (Requirement 11.4, 11.6)
        $this->logAccess($kpiType, $filters);
        
        // Route to appropriate method based on KPI type
        $result = match($kpiType) {
            'total_members' => $this->getTotalMembers($filters, $limit, $offset, $sortBy, $sortOrder),
            'active_members' => $this->getActiveMembers($filters, $limit, $offset, $sortBy, $sortOrder),
            'paid_members' => $this->getPaidMembers($filters, $limit, $offset, $sortBy, $sortOrder),
            'unpaid_members' => $this->getUnpaidMembers($filters, $limit, $offset, $sortBy, $sortOrder),
            'new_today' => $this->getNewToday($filters, $limit, $offset, $sortBy, $sortOrder),
            'new_registrants' => $this->getNewRegistrants($filters, $limit, $offset, $sortBy, $sortOrder),
            'total_admins' => $this->getTotalAdmins($filters, $limit, $offset, $sortBy, $sortOrder),
            'all_centers' => $this->getAllCenters($filters, $limit, $offset, $sortBy, $sortOrder),
            default => throw new Exception("Unsupported KPI type: {$kpiType}")
        };
        
        // Sanitize response to remove sensitive fields (Requirement 11.3)
        $result['data'] = $this->sanitizeResponse($result['data']);
        
        // Format and return response
        return $this->formatResponse($result['data'], $result['total'], $page, $limit, $sortBy, $sortOrder);
    }
    
    /**
     * Verify user has superadmin access
     * 
     * Requirements: 11.1, 11.2
     * 
     * @throws Exception
     */
    private function verifyAccess(): void {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $this->logUnauthorizedAccess('No session');
            http_response_code(403);
            throw new Exception("Unauthorized: User not logged in");
        }
        
        // Check if user has superadmin role
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'superadmin') {
            $this->logUnauthorizedAccess('Insufficient permissions');
            http_response_code(403);
            throw new Exception("Unauthorized: Superadmin access required");
        }
    }
    
    /**
     * Get total members with filters
     */
    private function getTotalMembers(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE 1=1";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get active members with filters
     */
    private function getActiveMembers(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.status = 'active'";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get paid members with filters
     */
    private function getPaidMembers(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.payment_status = 'paid'";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get unpaid members with filters
     */
    private function getUnpaidMembers(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.payment_status = 'unpaid'";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get members created today with filters
     */
    private function getNewToday(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE DATE(m.created_at) = CURDATE()";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get members created in last 30 days with filters
     */
    private function getNewRegistrants(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                m.id,
                m.member_id,
                m.full_name,
                m.email,
                m.gender,
                m.mobile_phone,
                m.membership_date,
                m.payment_status,
                m.status,
                c.name as center_name,
                c.region,
                m.created_at
            FROM members m
            LEFT JOIN centers c ON m.center_id = c.id
            WHERE m.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'member');
    }
    
    /**
     * Get total admins with filters
     */
    private function getTotalAdmins(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                u.id,
                u.email,
                u.full_name,
                u.role,
                u.status,
                u.last_login,
                u.created_at,
                NULL as center_name,
                NULL as region
            FROM users u
            WHERE u.role IN ('admin', 'superadmin')";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'admin');
    }
    
    /**
     * Get all centers with filters
     * 
     * @param array $filters Filter criteria
     * @param int $limit Records per page
     * @param int $offset Starting offset
     * @param string|null $sortBy Column to sort by
     * @param string $sortOrder Sort direction
     * @return array Data and total count
     */
    private function getAllCenters(array $filters, int $limit, int $offset, ?string $sortBy, string $sortOrder): array {
        $baseQuery = "SELECT 
                c.id,
                c.name,
                c.code,
                c.address,
                c.city,
                c.region,
                c.status,
                c.contact_person,
                COUNT(m.id) as total_members,
                c.created_at
            FROM centers c
            LEFT JOIN members m ON c.id = m.center_id AND m.status = 'active'
            WHERE 1=1";
        
        return $this->executeQuery($baseQuery, $filters, $limit, $offset, $sortBy, $sortOrder, 'center');
    }
    
    /**
     * Execute query with filters, sorting, and pagination
     */
    private function executeQuery(
        string $baseQuery,
        array $filters,
        int $limit,
        int $offset,
        ?string $sortBy,
        string $sortOrder,
        string $entityType
    ): array {
        // Build WHERE clause from filters
        list($whereClause, $params) = $this->buildWhereClause($filters, $entityType);
        
        // Add WHERE clause to base query
        $dataQuery = $baseQuery . $whereClause;
        
        // Add GROUP BY for centers (needed for COUNT aggregation)
        if ($entityType === 'center') {
            $dataQuery .= " GROUP BY c.id";
        }
        
        // Add sorting
        if ($sortBy) {
            $dataQuery .= $this->buildSortClause($sortBy, $sortOrder, $entityType);
        } else {
            // Default sort
            if ($entityType === 'center') {
                $dataQuery .= " ORDER BY c.name ASC";
            } elseif ($entityType === 'admin') {
                $dataQuery .= " ORDER BY u.created_at DESC";
            } else {
                $dataQuery .= " ORDER BY m.created_at DESC";
            }
        }
        
        // Add pagination
        $dataQuery .= " LIMIT :limit OFFSET :offset";
        
        // Get total count
        $countQuery = $this->buildCountQuery($baseQuery . $whereClause);
        $total = $this->getTotalCount($countQuery, $params);
        
        // Execute data query
        $stmt = $this->db->prepare($dataQuery);
        
        // Bind filter parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        // Bind pagination parameters
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        
        $stmt->execute();
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return ['data' => $data, 'total' => $total];
    }
    
    /**
     * Build WHERE clause from filters
     */
    private function buildWhereClause(array $filters, string $entityType): array {
        $conditions = [];
        $params = [];
        
        if ($entityType === 'center') {
            // Center-specific filters
            // Region filter
            if (isset($filters['region']) && $filters['region'] !== '') {
                $conditions[] = "c.region = :region";
                $params[':region'] = $filters['region'];
            }
            
            // Status filter
            if (isset($filters['status']) && $filters['status'] !== '') {
                $conditions[] = "c.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            // Search filter - search across name, code, and address (Requirement 7.2, 7.4)
            if (isset($filters['search']) && $filters['search'] !== '') {
                $searchTerm = '%' . $filters['search'] . '%';
                $conditions[] = "(c.name LIKE :search OR c.code LIKE :search OR c.address LIKE :search)";
                $params[':search'] = $searchTerm;
            }
        } elseif ($entityType === 'member') {
            // Member-specific filters
            $prefix = 'm';
            
            // Center filter
            if (isset($filters['center_id']) && $filters['center_id'] !== '') {
                $conditions[] = "{$prefix}.center_id = :center_id";
                $params[':center_id'] = $filters['center_id'];
            }
            
            // Region filter
            if (isset($filters['region']) && $filters['region'] !== '') {
                $conditions[] = "c.region = :region";
                $params[':region'] = $filters['region'];
            }
            
            // Payment status filter
            if (isset($filters['payment_status']) && $filters['payment_status'] !== '') {
                $conditions[] = "m.payment_status = :payment_status";
                $params[':payment_status'] = $filters['payment_status'];
            }
            
            // Status filter
            if (isset($filters['status']) && $filters['status'] !== '') {
                $conditions[] = "m.status = :status";
                $params[':status'] = $filters['status'];
            }
            
            // Gender filter
            if (isset($filters['gender']) && $filters['gender'] !== '') {
                $conditions[] = "m.gender = :gender";
                $params[':gender'] = $filters['gender'];
            }
            
            // Date range filters
            if (isset($filters['date_from']) && $filters['date_from'] !== '') {
                $conditions[] = "DATE(m.created_at) >= :date_from";
                $params[':date_from'] = $filters['date_from'];
            }
            
            if (isset($filters['date_to']) && $filters['date_to'] !== '') {
                $conditions[] = "DATE(m.created_at) <= :date_to";
                $params[':date_to'] = $filters['date_to'];
            }
        } else {
            // Admin-specific filters
            if (isset($filters['role']) && $filters['role'] !== '') {
                $conditions[] = "u.role = :role";
                $params[':role'] = $filters['role'];
            }
            
            if (isset($filters['status']) && $filters['status'] !== '') {
                $conditions[] = "u.status = :status";
                $params[':status'] = $filters['status'];
            }
        }
        
        $whereClause = empty($conditions) ? '' : ' AND ' . implode(' AND ', $conditions);
        
        return [$whereClause, $params];
    }
    
    /**
     * Build sort clause
     */
    private function buildSortClause(string $sortBy, string $sortOrder, string $entityType): string {
        if ($entityType === 'center') {
            // Handle special case for total_members (aggregated column)
            if ($sortBy === 'total_members') {
                return " ORDER BY total_members {$sortOrder}";
            }
            return " ORDER BY c.{$sortBy} {$sortOrder}";
        } elseif ($entityType === 'admin') {
            // Handle special cases for joined columns
            if ($sortBy === 'center_name' || $sortBy === 'region') {
                return " ORDER BY c.{$sortBy} {$sortOrder}";
            }
            return " ORDER BY u.{$sortBy} {$sortOrder}";
        } else {
            // Member entity type
            // Handle special cases for joined columns
            if ($sortBy === 'center_name' || $sortBy === 'region') {
                return " ORDER BY c.{$sortBy} {$sortOrder}";
            }
            return " ORDER BY m.{$sortBy} {$sortOrder}";
        }
    }
    
    /**
     * Build count query from data query
     */
    private function buildCountQuery(string $dataQuery): string {
        // Extract FROM and WHERE clauses
        $fromPos = stripos($dataQuery, 'FROM');
        $groupPos = stripos($dataQuery, 'GROUP BY');
        $orderPos = stripos($dataQuery, 'ORDER BY');
        $limitPos = stripos($dataQuery, 'LIMIT');
        
        // Determine end position (before GROUP BY, ORDER BY, or LIMIT)
        if ($groupPos !== false) {
            $endPos = $groupPos;
        } elseif ($orderPos !== false) {
            $endPos = $orderPos;
        } elseif ($limitPos !== false) {
            $endPos = $limitPos;
        } else {
            $endPos = strlen($dataQuery);
        }
        
        $fromClause = substr($dataQuery, $fromPos, $endPos - $fromPos);
        
        // If there's a GROUP BY, we need to count distinct groups
        if ($groupPos !== false) {
            // Extract the GROUP BY clause
            $groupEndPos = $orderPos !== false ? $orderPos : ($limitPos !== false ? $limitPos : strlen($dataQuery));
            $groupClause = substr($dataQuery, $groupPos, $groupEndPos - $groupPos);
            
            // For grouped queries, count distinct groups using a subquery
            return "SELECT COUNT(*) as total FROM (SELECT 1 " . $fromClause . " " . $groupClause . ") as grouped_count";
        }
        
        return "SELECT COUNT(*) as total " . $fromClause;
    }
    
    /**
     * Get total count for query
     */
    private function getTotalCount(string $countQuery, array $params): int {
        $stmt = $this->db->prepare($countQuery);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return (int)($result['total'] ?? 0);
    }
    
    /**
     * Validate and sanitize filters
     */
    private function validateFilters(array $filters, string $kpiType): array {
        if ($kpiType === 'all_centers') {
            $validKeys = self::CENTER_FILTER_KEYS;
        } elseif ($kpiType === 'total_admins') {
            $validKeys = self::ADMIN_FILTER_KEYS;
        } else {
            $validKeys = self::MEMBER_FILTER_KEYS;
        }
        
        $sanitized = [];
        
        foreach ($filters as $key => $value) {
            // Only allow valid filter keys
            if (!in_array($key, $validKeys)) {
                continue;
            }
            
            // Sanitize value based on type
            if ($key === 'center_id') {
                $sanitized[$key] = filter_var($value, FILTER_VALIDATE_INT);
            } elseif ($key === 'date_from' || $key === 'date_to') {
                // Validate date format (YYYY-MM-DD)
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                    $sanitized[$key] = $value;
                }
            } else {
                // String values - sanitize
                $sanitized[$key] = htmlspecialchars(strip_tags($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Sanitize sort column to prevent SQL injection
     */
    private function sanitizeSortColumn(?string $column, string $kpiType): ?string {
        if ($column === null) {
            return null;
        }
        
        if ($kpiType === 'all_centers') {
            $validColumns = self::CENTER_SORT_COLUMNS;
        } elseif ($kpiType === 'total_admins') {
            $validColumns = self::ADMIN_SORT_COLUMNS;
        } else {
            $validColumns = self::MEMBER_SORT_COLUMNS;
        }
        
        // Only allow whitelisted columns
        if (!in_array($column, $validColumns)) {
            return null;
        }
        
        return $column;
    }
    
    /**
     * Sanitize response to remove sensitive fields
     * 
     * Requirements: 11.3, 11.5
     */
    private function sanitizeResponse(array $data): array {
        foreach ($data as &$record) {
            foreach (self::SENSITIVE_FIELDS as $field) {
                if (isset($record[$field])) {
                    unset($record[$field]);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Format response with metadata
     */
    private function formatResponse(
        array $data,
        int $total,
        int $page,
        int $limit,
        ?string $sortBy,
        string $sortOrder
    ): array {
        return [
            'success' => true,
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => $total > 0 ? (int)ceil($total / $limit) : 0,
            'sort' => [
                'column' => $sortBy,
                'order' => $sortOrder
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Log drilldown access for audit trail
     * 
     * Requirements: 11.4, 11.6
     */
    private function logAccess(string $kpiType, array $filters): void {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        
        $userId = $_SESSION['user_id'];
        
        try {
            // Log directly to audit_logs table using PDO
            $stmt = $this->db->prepare("
                INSERT INTO audit_logs (action, entity_type, entity_id, user_id, details, ip_address, user_agent, created_at)
                VALUES (:action, 'analytics', NULL, :user_id, :details, :ip_address, :user_agent, NOW())
            ");
            
            $detailsJson = json_encode([
                'kpi_type' => $kpiType,
                'filters' => $filters,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt->execute([
                ':action' => 'drilldown_access',
                ':user_id' => $userId,
                ':details' => $detailsJson,
                ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log drilldown access: " . $e->getMessage());
        }
    }
    
    /**
     * Log unauthorized access attempt
     * 
     * Requirements: 11.6
     */
    private function logUnauthorizedAccess(string $reason): void {
        $userId = $_SESSION['user_id'] ?? null;
        
        try {
            // Log directly to audit_logs table using PDO
            // Only log if we have a valid user_id (foreign key constraint)
            if ($userId && $userId > 0) {
                $stmt = $this->db->prepare("
                    INSERT INTO audit_logs (action, entity_type, entity_id, user_id, details, ip_address, user_agent, created_at)
                    VALUES (:action, 'analytics', NULL, :user_id, :details, :ip_address, :user_agent, NOW())
                ");
                
                $detailsJson = json_encode([
                    'reason' => $reason,
                    'session_role' => $_SESSION['role'] ?? 'none',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                
                $stmt->execute([
                    ':action' => 'drilldown_unauthorized',
                    ':user_id' => $userId,
                    ':details' => $detailsJson,
                    ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
                ]);
            } else {
                // Log to error log instead when no valid user_id
                error_log(sprintf(
                    "Unauthorized drilldown access attempt: %s | IP: %s | Session Role: %s",
                    $reason,
                    $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    $_SESSION['role'] ?? 'none'
                ));
            }
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log("Failed to log unauthorized access: " . $e->getMessage());
        }
    }
}
