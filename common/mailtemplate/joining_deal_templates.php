<?php
/**
 * Master Joining Deal Email Templates Loader
 * Centralized template management for all joining deal emails
 */

function getJoiningDealEmailTemplate($joining_deal, $user_name, $user_email) {
    $template_file = '';
    $function_name = '';
    
    switch($joining_deal) {
        case 'CREATOR':
            $template_file = 'joining_deal_creator.php';
            $function_name = 'getCreatorJoiningDealEmail';
            break;
            
        case 'BASIC_FREE':
            $template_file = 'joining_deal_basic_free.php';
            $function_name = 'getBasicFreeJoiningDealEmail';
            break;
            
        case 'STANDARD':
            $template_file = 'joining_deal_standard.php';
            $function_name = 'getStandardJoiningDealEmail';
            break;
            
        case 'PREMIUM':
            $template_file = 'joining_deal_premium.php';
            $function_name = 'getPremiumJoiningDealEmail';
            break;
            
        default:
            return false;
    }
    
    $template_path = __DIR__ . '/' . $template_file;
    
    if(file_exists($template_path)) {
        require_once($template_path);
        if(function_exists($function_name)) {
            return call_user_func($function_name, $user_name, $user_email);
        }
    }
    
    return false;
}

/**
 * Get all available joining deal types
 */
function getAvailableJoiningDealTypes() {
    return [
        'CREATOR' => 'Creator Collaboration Partnership',
        'BASIC_FREE' => 'Digital Marketing Partner',
        'STANDARD' => 'Franchise Distributor Partner (Standard)',
        'PREMIUM' => 'Franchise Distributor Partner (Premium)'
    ];
}

/**
 * Validate joining deal type
 */
function isValidJoiningDealType($joining_deal) {
    $available_types = array_keys(getAvailableJoiningDealTypes());
    return in_array($joining_deal, $available_types);
}
?>
