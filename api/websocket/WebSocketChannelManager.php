<?php
/**
 * WebSocket Channel Manager
 * Manages channel subscriptions and message broadcasting
 * Requirements: 6.1, 6.2, 6.3
 */

class WebSocketChannelManager {
    private $channels = [];
    private $roleChannels = [
        'superadmin' => [
            'system.health',
            'security.alerts',
            'user.activity',
            'system.performance',
            'database.metrics',
            'audit.logs'
        ],
        'admin' => [
            'center.{centerId}.activities',
            'member.updates',
            'contribution.notifications',
            'center.{centerId}.alerts'
        ],
        'user' => [
            'user.{userId}.notifications',
            'center.{centerId}.announcements',
            'user.{userId}.updates'
        ]
    ];
    
    /**
     * Get allowed channels for user role
     */
    public function getAllowedChannels($role, $userId = null, $centerId = null) {
        if (!isset($this->roleChannels[$role])) {
            return [];
        }
        
        $channels = $this->roleChannels[$role];
        $allowedChannels = [];
        
        foreach ($channels as $channel) {
            // Replace placeholders
            $processedChannel = str_replace('{userId}', $userId, $channel);
            $processedChannel = str_replace('{centerId}', $centerId, $processedChannel);
            
            $allowedChannels[] = $processedChannel;
        }
        
        return $allowedChannels;
    }
    
    /**
     * Check if user can subscribe to channel
     */
    public function canSubscribe($channel, $role, $userId = null, $centerId = null) {
        $allowedChannels = $this->getAllowedChannels($role, $userId, $centerId);
        
        // Check exact match
        if (in_array($channel, $allowedChannels)) {
            return true;
        }
        
        // Check pattern match
        foreach ($allowedChannels as $allowedChannel) {
            if ($this->matchesPattern($channel, $allowedChannel)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Match channel against pattern
     */
    private function matchesPattern($channel, $pattern) {
        // Convert pattern to regex
        $regex = str_replace('.', '\.', $pattern);
        $regex = str_replace('*', '.*', $regex);
        $regex = '/^' . $regex . '$/';
        
        return preg_match($regex, $channel) === 1;
    }
    
    /**
     * Add subscriber to channel
     */
    public function subscribe($channel, $client) {
        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }
        
        if (!in_array($client, $this->channels[$channel])) {
            $this->channels[$channel][] = $client;
        }
    }
    
    /**
     * Remove subscriber from channel
     */
    public function unsubscribe($channel, $client) {
        if (isset($this->channels[$channel])) {
            $key = array_search($client, $this->channels[$channel]);
            if ($key !== false) {
                unset($this->channels[$channel][$key]);
            }
            
            // Clean up empty channels
            if (empty($this->channels[$channel])) {
                unset($this->channels[$channel]);
            }
        }
    }
    
    /**
     * Remove client from all channels
     */
    public function unsubscribeAll($client) {
        foreach ($this->channels as $channel => $subscribers) {
            $this->unsubscribe($channel, $client);
        }
    }
    
    /**
     * Get subscribers for channel
     */
    public function getSubscribers($channel) {
        return $this->channels[$channel] ?? [];
    }
    
    /**
     * Get all channels
     */
    public function getAllChannels() {
        return array_keys($this->channels);
    }
    
    /**
     * Get subscriber count for channel
     */
    public function getSubscriberCount($channel) {
        return count($this->channels[$channel] ?? []);
    }
    
    /**
     * Get total subscriber count
     */
    public function getTotalSubscribers() {
        $total = 0;
        foreach ($this->channels as $subscribers) {
            $total += count($subscribers);
        }
        return $total;
    }
    
    /**
     * Get channel statistics
     */
    public function getStatistics() {
        $stats = [
            'total_channels' => count($this->channels),
            'total_subscriptions' => $this->getTotalSubscribers(),
            'channels' => []
        ];
        
        foreach ($this->channels as $channel => $subscribers) {
            $stats['channels'][$channel] = count($subscribers);
        }
        
        return $stats;
    }
}
?>
