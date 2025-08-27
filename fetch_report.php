<?php
require 'db.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$circle = $input['circle'] ?? '';
$region = $input['region'] ?? '';
$division = $input['division'] ?? '';
$start = $input['start'] ?? '';
$end = $input['end'] ?? '';
$reportType = $input['reportType'] ?? 'open';
$export = $input['export'] ?? '';

// New account range filter parameters
$accountRangeFilter = $input['accountRangeFilter'] ?? false;
$officeType = $input['officeType'] ?? 'all';
$minAccounts = $input['minAccounts'] ?? 0;
$maxAccounts = $input['maxAccounts'] ?? 999999;

if (!$start || !$end) {
    echo json_encode(['error' => 'Please select start and end date.']);
    exit;
}

switch ($reportType) {
    case 'open':
        $title = "Account Opened Details";
        break;
    case 'close':
        $title = "Account Closed Details";
        break;
    case 'net':
        $title = "Net Addition Report";
        break;
    case 'nil':
        $title = "NIL Account Offices";
        break;
    case 'subdivision':
        $title = "Sub Division wise Account Opened Status";
        break;
    default:
        $title = "Unknown Report Type";
        break;
}

if (!$division && !$region && !$circle) {
    echo json_encode(['error' => 'Please select at least one filter.']);
    exit;
}

// Handle Sub Division wise Account Opened Status WITH SORTING FUNCTIONALITY
if ($reportType === 'subdivision') {
    $params = [$start, $end];
    $sql = "";
    $filterText = "";
    
    // Build query based on filter level
    if ($division) {
        // Division level: Show subdivision data within that division
        $sql = "
            SELECT 
                d.division_name AS `Division Name`,
                d.subdivision_name AS `Sub Division Name`,
                COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS `Total Accounts Opened`
            FROM data d
            LEFT JOIN transactions t ON 
                d.division_name = t.division_name AND 
                d.office_name = t.office_name AND 
                d.so_name = t.so_name AND 
                t.upload_date BETWEEN ? AND ?
            WHERE d.division_name = ?
            GROUP BY d.division_name, d.subdivision_name
            ORDER BY d.subdivision_name
        ";
        $params[] = $division;
        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Division: ".htmlspecialchars($division)."</span>";
    } 
    elseif ($region) {
        // Region level: Show subdivision data for all divisions in the region
        $sql = "
            SELECT 
                d.division_name AS `Division Name`,
                d.subdivision_name AS `Sub Division Name`,
                COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS `Total Accounts Opened`
            FROM data d
            LEFT JOIN transactions t ON 
                d.division_name = t.division_name AND 
                d.office_name = t.office_name AND 
                d.so_name = t.so_name AND 
                t.upload_date BETWEEN ? AND ?
            WHERE d.region_name = ?
            GROUP BY d.division_name, d.subdivision_name
            ORDER BY d.division_name, d.subdivision_name
        ";
        $params[] = $region;
        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Region: ".htmlspecialchars($region)."</span>";
    }
    elseif ($circle) {
        // Circle level: Show subdivision data for all divisions in the circle
        $sql = "
            SELECT 
                d.division_name AS `Division Name`,
                d.subdivision_name AS `Sub Division Name`,
                COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS `Total Accounts Opened`
            FROM data d
            LEFT JOIN transactions t ON 
                d.division_name = t.division_name AND 
                d.office_name = t.office_name AND 
                d.so_name = t.so_name AND 
                t.upload_date BETWEEN ? AND ?
            WHERE d.circle_name = ?
            GROUP BY d.division_name, d.subdivision_name
            ORDER BY d.division_name, d.subdivision_name
        ";
        $params[] = $circle;
        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Circle: ".htmlspecialchars($circle)."</span>";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$rows) {
            echo "<div style='text-align: center; padding: 30px; color: #666; background: linear-gradient(to right, #ffebee, #f3e5f5); border-radius: 12px; margin: 20px;'>";
            echo "<h3 style='color: #c62828; margin-bottom: 10px;'>🔍 No Data Found</h3>";
            echo "<p style='font-size: 16px;'>No subdivision data found for the selected filters and date range.</p>";
            echo "</div>";
            exit;
        }
        
        // Generate enhanced HTML output WITH SORTING FUNCTIONALITY
        $html = "<div style='background: linear-gradient(to right, #f8f9fa, #ffffff); border-radius: 12px; padding: 25px; margin: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>";
        $html .= "<h3 style='color: #2e7d32; text-align: center; margin-bottom: 20px; font-size: 24px;'>📍 " . htmlspecialchars($title) . "</h3>";
        
        // Filter info
        $html .= "<div style='background: linear-gradient(to right, #e8f5e9, #f3e5f5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>";
        $html .= "<p style='margin: 5px 0; color: #2e7d32; font-weight: 600;'>📅 Date Range: <span style='background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;'>" . htmlspecialchars($start) . " to " . htmlspecialchars($end) . "</span></p>";
        $html .= "<p style='margin: 5px 0; color: #1976d2; font-weight: 600;'>📍 " . $filterText . "</p>";
        $html .= "</div>";
        
        $html .= "<div style='overflow-x: auto;'>";
        $html .= "<table id='subdivisionTable' border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>";
        $html .= "<thead style='background: linear-gradient(to right, #6a1b9a, #8e24aa);'><tr>";
        
        // Table headers with icons and sorting for Total Accounts Opened
        $icons = [
            'Division Name' => '🏢',
            'Sub Division Name' => '📍', 
            'Total Accounts Opened' => '📈'
        ];
        
        foreach (array_keys($rows[0]) as $col) {
            $icon = $icons[$col] ?? '📊';
            
            if ($col === 'Total Accounts Opened') {
                // Add sorting icons for Total Accounts Opened column
                $html .= "<th style='padding: 12px; border: 1px solid #ddd; color: white; font-weight: 600; text-align: center;'>";
                $html .= $icon . " " . htmlspecialchars($col);
                $html .= "<div style='display: inline-flex; flex-direction: column; margin-left: 8px; gap: 2px;'>";
                $html .= "<span id='sortDesc' onclick='sortSubdivisionTable(\"desc\")' style='font-size: 12px; cursor: pointer; transition: all 0.2s ease; color: #2e7d32; font-weight: bold; text-decoration: none; line-height: 1;' title='Highest to Lowest'>▲</span>";
                $html .= "<span id='sortAsc' onclick='sortSubdivisionTable(\"asc\")' style='font-size: 12px; cursor: pointer; transition: all 0.2s ease; color: #999; text-decoration: none; line-height: 1;' title='Lowest to Highest'>▼</span>";
                $html .= "</div>";
                $html .= "</th>";
            } else {
                $html .= "<th style='padding: 12px; border: 1px solid #ddd; color: white; font-weight: 600; text-align: center;'>" . $icon . " " . htmlspecialchars($col) . "</th>";
            }
        }
        $html .= "</tr></thead><tbody>";
        
        // Table rows
        $rowIndex = 0;
        $totalAccounts = 0;
        $totalSubdivisions = 0;
        
        foreach ($rows as $row) {
            $bgColor = $rowIndex % 2 === 0 ? '#f9f9f9' : '#ffffff';
            $html .= "<tr style='background-color: " . $bgColor . ";'>";
            
            foreach ($row as $key => $val) {
                $textAlign = ($key === 'Total Accounts Opened') ? 'center' : 'left';
                $fontWeight = ($key === 'Total Accounts Opened') ? 'bold' : 'normal';
                $color = ($key === 'Total Accounts Opened') ? '#2e7d32' : '#333';
                
                // Format numbers with commas for better readability
                $displayVal = ($key === 'Total Accounts Opened') ? number_format($val) : htmlspecialchars($val);
                
                $html .= "<td style='padding: 10px; border: 1px solid #ddd; text-align: " . $textAlign . "; font-weight: " . $fontWeight . "; color: " . $color . ";'>" . $displayVal . "</td>";
            }
            $html .= "</tr>";
            $rowIndex++;
            
            // Calculate totals
            $totalAccounts += $row['Total Accounts Opened'];
            $totalSubdivisions++;
        }
        
        $html .= "</tbody></table></div>";
        
        // Summary statistics
        $avgAccounts = $totalSubdivisions > 0 ? round($totalAccounts / $totalSubdivisions, 2) : 0;
        
        $html .= "<div style='margin-top: 20px; padding: 20px; background: linear-gradient(to right, #e8f5e9, #f3e5f5); border-radius: 10px; text-align: center;'>";
        $html .= "<h4 style='color: #2e7d32; margin-bottom: 15px; font-size: 20px;'>📊 Summary Statistics</h4>";
        $html .= "<div style='display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px;'>";
        
        $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
        $html .= "<div style='font-size: 24px; font-weight: bold; color: #1976d2;'>" . $totalSubdivisions . "</div>";
        $html .= "<div style='color: #666; font-size: 14px;'>Total Subdivisions</div>";
        $html .= "</div>";
        
        $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
        $html .= "<div style='font-size: 24px; font-weight: bold; color: #388e3c;'>" . number_format($totalAccounts) . "</div>";
        $html .= "<div style='color: #666; font-size: 14px;'>Total Accounts</div>";
        $html .= "</div>";
        
        $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
        $html .= "<div style='font-size: 24px; font-weight: bold; color: #f57c00;'>" . number_format($avgAccounts, 2) . "</div>";
        $html .= "<div style='color: #666; font-size: 14px;'>Average per Subdivision</div>";
        $html .= "</div>";
        
        $html .= "</div>";
        
        // Add sorting instructions
        $html .= "<div style='margin-top: 15px; text-align: center; color: #666; font-size: 14px;'>";
        $html .= "💡 <strong>Tip:</strong> Click the ▲ or ▼ arrows in the 'Total Accounts Opened' column to sort the data.";
        $html .= "</div>";
        
        $html .= "</div></div>";
        
        echo $html;
        exit;
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle Account Range Filter for 'open' report type
if ($accountRangeFilter && $reportType === 'open') {
    // Normalize numeric inputs
    $minAccounts = is_numeric($minAccounts) ? (int)$minAccounts : 0;
    $maxAccounts = is_numeric($maxAccounts) ? (int)$maxAccounts : 999999;

    // Guard
    if ($minAccounts > $maxAccounts) {
        echo "<div style='padding:20px;color:#b71c1c;background:#ffebee;border-radius:8px;margin:20px;'>Invalid account range.</div>";
        exit;
    }

    $params = [];
    $sql = "";
    $filterText = "";

    if ($division) {
        // Division: detailed office rows within range
        $sql = "
            SELECT 
                d.division_name AS `Division Name`,
                d.subdivision_name AS `Sub Division Name`,
                d.so_name AS `HO/SO Name`,
                d.office_name AS `Office Name`,
                COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS `Total Account Opened`
            FROM data d
            LEFT JOIN transactions t
              ON d.division_name = t.division_name
             AND d.office_name   = t.office_name
             AND d.so_name       = t.so_name
             AND t.upload_date BETWEEN ? AND ?
            WHERE d.division_name = ?
            ".($officeType !== 'all' ? " AND d.office_type = ? " : "")."
            GROUP BY d.division_name, d.subdivision_name, d.so_name, d.office_name
            HAVING COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) BETWEEN ? AND ?
            ORDER BY `Total Account Opened` DESC
        ";
        $params = [$start, $end, $division];
        if ($officeType !== 'all') $params[] = $officeType;
        $params[] = $minAccounts; 
        $params[] = $maxAccounts;

        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Division: ".htmlspecialchars($division)."</span>";
    }
    elseif ($region) {
        // Region: division-wise aggregation based on per-office totals in range
        $sql = "
            SELECT
                ot.division_name AS `Division Name`,
                COUNT(CASE WHEN COALESCE(ot.total_accounts,0) BETWEEN ? AND ? THEN 1 END) AS `Office Count`,
                COALESCE(SUM(CASE WHEN COALESCE(ot.total_accounts,0) BETWEEN ? AND ? THEN ot.total_accounts END), 0) AS `Total Account Opened`
            FROM (
                SELECT
                    d.division_name,
                    d.office_name,
                    d.so_name,
                    d.office_type,
                    COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS total_accounts
                FROM data d
                LEFT JOIN transactions t
                  ON d.division_name = t.division_name
                 AND d.office_name   = t.office_name
                 AND d.so_name       = t.so_name
                 AND t.upload_date BETWEEN ? AND ?
                WHERE d.division_name IN (SELECT DISTINCT d2.division_name FROM data d2 WHERE d2.region_name = ?)
                ".($officeType !== 'all' ? " AND d.office_type = ? " : "")."
                GROUP BY d.division_name, d.office_name, d.so_name, d.office_type
            ) ot
            GROUP BY ot.division_name
            HAVING COUNT(CASE WHEN COALESCE(ot.total_accounts,0) BETWEEN ? AND ? THEN 1 END) > 0
            ORDER BY ot.division_name
        ";
        
        $params = [$minAccounts, $maxAccounts, $minAccounts, $maxAccounts, $start, $end, $region];
        if ($officeType !== 'all') $params[] = $officeType;
        $params[] = $minAccounts; 
        $params[] = $maxAccounts;

        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Region: ".htmlspecialchars($region)."</span>";
    }
    elseif ($circle) {
        // Circle: TOTAL offices in range (fixed to match region counting logic)
        $sql = "
            SELECT 
                COUNT(*) AS `Total Offices in Range`
            FROM (
                SELECT
                    d.region_name,
                    d.division_name,
                    d.office_name,
                    d.so_name,
                    d.office_type,
                    COALESCE(SUM(t.mis + t.ppfgp + t.ssa + t.rd + t.sbbas + t.sbsgp + t.scss + t.td), 0) AS total_accounts
                FROM data d
                LEFT JOIN transactions t
                  ON d.division_name = t.division_name
                 AND d.office_name   = t.office_name
                 AND d.so_name       = t.so_name
                 AND t.upload_date BETWEEN ? AND ?
                WHERE d.circle_name = ?
                ".($officeType !== 'all' ? " AND d.office_type = ? " : "")."
                GROUP BY d.region_name, d.division_name, d.office_name, d.so_name, d.office_type
            ) ot
            WHERE COALESCE(ot.total_accounts,0) BETWEEN ? AND ?
        ";
        $params = [$start, $end, $circle];
        if ($officeType !== 'all') $params[] = $officeType;
        $params[] = $minAccounts; 
        $params[] = $maxAccounts;

        $filterText = "<span style='background:#2196f3;color:#fff;padding:2px 6px;border-radius:4px;'>Circle: ".htmlspecialchars($circle)."</span>";
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$rows || (isset($rows[0]['Total Offices in Range']) && $rows[0]['Total Offices in Range'] == 0) || 
            (isset($rows[0]['Office Count']) && array_sum(array_column($rows, 'Office Count')) == 0)) {
            echo "<div style='text-align: center; padding: 30px; color: #666; background: linear-gradient(to right, #ffebee, #f3e5f5); border-radius: 12px; margin: 20px;'>";
            echo "<h3 style='color: #c62828; margin-bottom: 10px;'>🔍 No Results Found</h3>";
            echo "<p style='font-size: 16px;'>No offices found with account range <strong>" . htmlspecialchars($minAccounts) . " to " . htmlspecialchars($maxAccounts) . "</strong></p>";
            if ($officeType !== 'all') {
                echo "<p style='font-size: 14px; color: #757575;'>Office Type: <strong>" . strtoupper(htmlspecialchars($officeType)) . "</strong></p>";
            }
            echo "</div>";
            exit;
        }
        
        // Enhanced HTML output
        $html = "<div style='background: linear-gradient(to right, #f8f9fa, #ffffff); border-radius: 12px; padding: 25px; margin: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);'>";
        $html .= "<h3 style='color: #2e7d32; text-align: center; margin-bottom: 20px; font-size: 24px;'>🏢 " . htmlspecialchars($title) . " - Account Range Filter Results</h3>";
        
        // Filter info
        $html .= "<div style='background: linear-gradient(to right, #e8f5e9, #f3e5f5); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>";
        $html .= "<p style='margin: 5px 0; color: #2e7d32; font-weight: 600;'>📊 Account Range: <span style='background: #4caf50; color: white; padding: 4px 8px; border-radius: 4px;'>" . htmlspecialchars($minAccounts) . " to " . htmlspecialchars($maxAccounts) . "</span></p>";
        if ($officeType !== 'all') {
            $html .= "<p style='margin: 5px 0; color: #7b1fa2; font-weight: 600;'>🏛️ Office Type: <span style='background: #9c27b0; color: white; padding: 4px 8px; border-radius: 4px;'>" . strtoupper(htmlspecialchars($officeType)) . "</span></p>";
        }
        
        // Add filter level info
        $html .= "<p style='margin: 5px 0; color: #1976d2; font-weight: 600;'>📍 " . $filterText . "</p>";
        $html .= "</div>";
        
        $html .= "<div style='overflow-x: auto;'>";
        $html .= "<table border='1' style='border-collapse: collapse; width: 100%; margin-top: 10px; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);'>";
        $html .= "<thead style='background: linear-gradient(to right, #6a1b9a, #8e24aa);'><tr>";
        
        // Table headers
        foreach (array_keys($rows[0]) as $col) {
            $icon = '';
            switch($col) {
                case 'Division Name': $icon = '🏢'; break;
                case 'Sub Division Name': $icon = '📍'; break;
                case 'HO/SO Name': $icon = '👔'; break;
                case 'Office Name': $icon = '🏛️'; break;
                case 'Total Account Opened': $icon = '📈'; break;
                case 'Office Count': $icon = '🔢'; break;
                case 'Total Offices in Range': $icon = '🎯'; break;
            }
            $html .= "<th style='padding: 12px; border: 1px solid #ddd; color: white; font-weight: 600; text-align: center;'>" . $icon . " " . htmlspecialchars($col) . "</th>";
        }
        $html .= "</tr></thead><tbody>";
        
        // Table rows
        $rowIndex = 0;
        $totalOffices = 0;
        $totalAccounts = 0;
        
        foreach ($rows as $row) {
            $bgColor = $rowIndex % 2 === 0 ? '#f9f9f9' : '#ffffff';
            $html .= "<tr style='background-color: " . $bgColor . ";'>";
            foreach ($row as $key => $val) {
                $textAlign = (in_array($key, ['Total Account Opened', 'Office Count', 'Total Offices in Range'])) ? 'center' : 'left';
                $fontWeight = (in_array($key, ['Total Account Opened', 'Office Count', 'Total Offices in Range'])) ? 'bold' : 'normal';
                $color = (in_array($key, ['Total Account Opened', 'Office Count', 'Total Offices in Range'])) ? '#2e7d32' : '#333';
                $html .= "<td style='padding: 10px; border: 1px solid #ddd; text-align: " . $textAlign . "; font-weight: " . $fontWeight . "; color: " . $color . ";'>" . htmlspecialchars($val) . "</td>";
            }
            $html .= "</tr>";
            $rowIndex++;
            
            // Calculate totals
            if (isset($row['Office Count'])) {
                $totalOffices += $row['Office Count'];
            } elseif (isset($row['Total Offices in Range'])) {
                $totalOffices = $row['Total Offices in Range'];
            } else {
                $totalOffices = count($rows); // For detailed view
            }
            
            if (isset($row['Total Account Opened'])) {
                $totalAccounts += $row['Total Account Opened'];
            }
        }
        
        $html .= "</tbody></table></div>";
        
        // Summary statistics
        $avgAccounts = $totalOffices > 0 ? round($totalAccounts / $totalOffices, 2) : 0;
        
        $html .= "<div style='margin-top: 20px; padding: 20px; background: linear-gradient(to right, #e8f5e9, #f3e5f5); border-radius: 10px; text-align: center;'>";
        $html .= "<h4 style='color: #2e7d32; margin-bottom: 15px; font-size: 20px;'>📊 Summary Statistics</h4>";
        $html .= "<div style='display: flex; justify-content: space-around; flex-wrap: wrap; gap: 15px;'>";
        
        if ($totalOffices > 0) {
            $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
            $html .= "<div style='font-size: 24px; font-weight: bold; color: #1976d2;'>" . $totalOffices . "</div>";
            $html .= "<div style='color: #666; font-size: 14px;'>Total Offices</div>";
            $html .= "</div>";
        }
        
        if ($totalAccounts > 0) {
            $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
            $html .= "<div style='font-size: 24px; font-weight: bold; color: #388e3c;'>" . $totalAccounts . "</div>";
            $html .= "<div style='color: #666; font-size: 14px;'>Total Accounts</div>";
            $html .= "</div>";
            
            $html .= "<div style='background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); min-width: 150px;'>";
            $html .= "<div style='font-size: 24px; font-weight: bold; color: #f57c00;'>" . $avgAccounts . "</div>";
            $html .= "<div style='color: #666; font-size: 14px;'>Average per Office</div>";
            $html .= "</div>";
        }
        
        $html .= "</div></div></div>";
        
        echo $html;
        exit;
        
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}

// Handle NIL Account report type (existing logic)
if ($reportType === 'nil') {
    $params = [$start, $end];
    $filter = "";
    $select = "";
    $group_by = "";
    
    if ($division) {
        $filter .= " AND d.division_name = ?";
        $params[] = $division;
        $select = "d.subdivision_name AS `Sub Division`, d.so_name AS `SO / HO Name`, d.office_name AS `Office Name`";
        $group_by = "d.subdivision_name, d.so_name, d.office_name";
    } elseif ($region) {
        $filter .= " AND d.division_name IN (SELECT DISTINCT d2.division_name FROM data d2 WHERE d2.region_name = ?)";
        $params[] = $region;
        $select = "d.division_name AS Division, COUNT(d.office_name) AS `NIL Office Count`";
        $group_by = "d.division_name";
    } elseif ($circle) {
        $filter .= " AND d.division_name IN (SELECT DISTINCT d2.division_name FROM data d2 WHERE d2.circle_name = ?)";
        $params[] = $circle;
        $select = "d.division_name AS Division, COUNT(d.office_name) AS `NIL Office Count`";
        $group_by = "d.division_name";
    }

    // Add office type filter for NIL report if specified
    if ($officeType && $officeType !== 'all') {
        $filter .= " AND d.office_type = ?";
        $params[] = $officeType;
    }

    $sql = "SELECT " . $select . " FROM data d LEFT JOIN transactions t ON d.division_name = t.division_name AND d.office_name = t.office_name AND d.so_name = t.so_name AND t.upload_date BETWEEN ? AND ? WHERE t.office_name IS NULL " . $filter . " GROUP BY " . $group_by . " ORDER BY " . $group_by;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode(['error' => 'No NIL offices found.']);
        exit;
    }

    $totalCount = 0;
    if ($division) {
        $totalCount = count($rows);
    } elseif ($region || $circle) {
        foreach ($rows as $row) {
            $totalCount += $row['NIL Office Count'];
        }
    }

    $html = "<h3>" . htmlspecialchars($title) . "</h3>";
    $html .= "<p><strong>Total NIL Offices: </strong>" . $totalCount . "</p>";

    $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    $html .= "<tr style='background-color: #f0f0f0;'>";
    foreach (array_keys($rows[0]) as $col) {
        $html .= "<th style='padding: 8px;'>" . htmlspecialchars($col) . "</th>";
    }
    $html .= "</tr>";

    foreach ($rows as $row) {
        $html .= "<tr>";
        foreach ($row as $val) {
            $html .= "<td style='padding: 8px; text-align: center;'>" . htmlspecialchars($val) . "</td>";
        }
        $html .= "</tr>";
    }
    $html .= "</table>";

    echo $html;
    exit;
}

// EXISTING BEHAVIOR FOR REGULAR REPORTS (open, close, net) - PRESERVED
$params = [$start, $end];

// Build the main query based on report type and selection level
if ($reportType === 'open') {
    if ($circle && !$region && !$division) {
        // For circle level, show region-wise aggregation with proper filtering
        $sql = "SELECT d.region_name as Region, SUM(t.mis) as MIS, SUM(t.ppfgp) as PPFGP, SUM(t.ssa) as SSA, SUM(t.rd) as RD, SUM(t.sbbas) as SBBAS, SUM(t.sbsgp) as SBSGP, SUM(t.scss) as SCSS, SUM(t.td) as TD, SUM(t.prfts) as PRFTS, SUM(t.kvn) as KVN, SUM(t.nsc8) as NSC8, SUM(t.mssc) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.circle_name = ? GROUP BY d.region_name ORDER BY d.region_name";
        $params[] = $circle;
    } elseif ($region && !$division) {
        // For region level, show division-wise data
        $sql = "SELECT t.division_name as Region, SUM(t.mis) as MIS, SUM(t.ppfgp) as PPFGP, SUM(t.ssa) as SSA, SUM(t.rd) as RD, SUM(t.sbbas) as SBBAS, SUM(t.sbsgp) as SBSGP, SUM(t.scss) as SCSS, SUM(t.td) as TD, SUM(t.prfts) as PRFTS, SUM(t.kvn) as KVN, SUM(t.nsc8) as NSC8, SUM(t.mssc) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.region_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $region;
    } elseif ($division) {
        // For division level, show division data
        $sql = "SELECT t.division_name as Region, SUM(t.mis) as MIS, SUM(t.ppfgp) as PPFGP, SUM(t.ssa) as SSA, SUM(t.rd) as RD, SUM(t.sbbas) as SBBAS, SUM(t.sbsgp) as SBSGP, SUM(t.scss) as SCSS, SUM(t.td) as TD, SUM(t.prfts) as PRFTS, SUM(t.kvn) as KVN, SUM(t.nsc8) as NSC8, SUM(t.mssc) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND t.division_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $division;
    }
} elseif ($reportType === 'close') {
    if ($circle && !$region && !$division) {
        $sql = "SELECT d.region_name as Region, SUM(t.mis_close) as MIS, SUM(t.ppfgp_close) as PPFGP, SUM(t.ssa_close) as SSA, SUM(t.rd_close) as RD, SUM(t.sbbas_close) as SBBAS, SUM(t.sbsgp_close) as SBSGP, SUM(t.scss_close) as SCSS, SUM(t.td_close) as TD, SUM(t.prfts_close) as PRFTS, SUM(t.kvn_close) as KVN, SUM(t.nsc8_close) as NSC8, SUM(t.mssc_close) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.circle_name = ? GROUP BY d.region_name ORDER BY d.region_name";
        $params[] = $circle;
    } elseif ($region && !$division) {
        $sql = "SELECT t.division_name as Region, SUM(t.mis_close) as MIS, SUM(t.ppfgp_close) as PPFGP, SUM(t.ssa_close) as SSA, SUM(t.rd_close) as RD, SUM(t.sbbas_close) as SBBAS, SUM(t.sbsgp_close) as SBSGP, SUM(t.scss_close) as SCSS, SUM(t.td_close) as TD, SUM(t.prfts_close) as PRFTS, SUM(t.kvn_close) as KVN, SUM(t.nsc8_close) as NSC8, SUM(t.mssc_close) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.region_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $region;
    } elseif ($division) {
        $sql = "SELECT t.division_name as Region, SUM(t.mis_close) as MIS, SUM(t.ppfgp_close) as PPFGP, SUM(t.ssa_close) as SSA, SUM(t.rd_close) as RD, SUM(t.sbbas_close) as SBBAS, SUM(t.sbsgp_close) as SBSGP, SUM(t.scss_close) as SCSS, SUM(t.td_close) as TD, SUM(t.prfts_close) as PRFTS, SUM(t.kvn_close) as KVN, SUM(t.nsc8_close) as NSC8, SUM(t.mssc_close) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND t.division_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $division;
    }
} else { // net
    if ($circle && !$region && !$division) {
        $sql = "SELECT d.region_name as Region, (SUM(t.mis) - SUM(t.mis_close)) as MIS, (SUM(t.ppfgp) - SUM(t.ppfgp_close)) as PPFGP, (SUM(t.ssa) - SUM(t.ssa_close)) as SSA, (SUM(t.rd) - SUM(t.rd_close)) as RD, (SUM(t.sbbas) - SUM(t.sbbas_close)) as SBBAS, (SUM(t.sbsgp) - SUM(t.sbsgp_close)) as SBSGP, (SUM(t.scss) - SUM(t.scss_close)) as SCSS, (SUM(t.td) - SUM(t.td_close)) as TD, (SUM(t.prfts) - SUM(t.prfts_close)) as PRFTS, (SUM(t.kvn) - SUM(t.kvn_close)) as KVN, (SUM(t.nsc8) - SUM(t.nsc8_close)) as NSC8, (SUM(t.mssc) - SUM(t.mssc_close)) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.circle_name = ? GROUP BY d.region_name ORDER BY d.region_name";
        $params[] = $circle;
    } elseif ($region && !$division) {
        $sql = "SELECT t.division_name as Region, (SUM(t.mis) - SUM(t.mis_close)) as MIS, (SUM(t.ppfgp) - SUM(t.ppfgp_close)) as PPFGP, (SUM(t.ssa) - SUM(t.ssa_close)) as SSA, (SUM(t.rd) - SUM(t.rd_close)) as RD, (SUM(t.sbbas) - SUM(t.sbbas_close)) as SBBAS, (SUM(t.sbsgp) - SUM(t.sbsgp_close)) as SBSGP, (SUM(t.scss) - SUM(t.scss_close)) as SCSS, (SUM(t.td) - SUM(t.td_close)) as TD, (SUM(t.prfts) - SUM(t.prfts_close)) as PRFTS, (SUM(t.kvn) - SUM(t.kvn_close)) as KVN, (SUM(t.nsc8) - SUM(t.nsc8_close)) as NSC8, (SUM(t.mssc) - SUM(t.mssc_close)) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND d.region_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $region;
    } elseif ($division) {
        $sql = "SELECT t.division_name as Region, (SUM(t.mis) - SUM(t.mis_close)) as MIS, (SUM(t.ppfgp) - SUM(t.ppfgp_close)) as PPFGP, (SUM(t.ssa) - SUM(t.ssa_close)) as SSA, (SUM(t.rd) - SUM(t.rd_close)) as RD, (SUM(t.sbbas) - SUM(t.sbbas_close)) as SBBAS, (SUM(t.sbsgp) - SUM(t.sbsgp_close)) as SBSGP, (SUM(t.scss) - SUM(t.scss_close)) as SCSS, (SUM(t.td) - SUM(t.td_close)) as TD, (SUM(t.prfts) - SUM(t.prfts_close)) as PRFTS, (SUM(t.kvn) - SUM(t.kvn_close)) as KVN, (SUM(t.nsc8) - SUM(t.nsc8_close)) as NSC8, (SUM(t.mssc) - SUM(t.mssc_close)) as MSSC FROM transactions t INNER JOIN data d ON t.division_name = d.division_name AND t.office_name = d.office_name AND t.so_name = d.so_name WHERE t.upload_date BETWEEN ? AND ? AND t.division_name = ? GROUP BY t.division_name ORDER BY t.division_name";
        $params[] = $division;
    }
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$rows) {
    echo json_encode(['error' => 'No data found.']);
    exit;
}

$html = "<h3>" . htmlspecialchars($title) . "</h3>";
$html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
$html .= "<tr style='background-color: #f0f0f0;'>";

// Headers
$productFields = ['MIS', 'PPFGP', 'SSA', 'RD', 'SBBAS', 'SBSGP', 'SCSS', 'TD', 'PRFTS', 'KVN', 'NSC8', 'MSSC'];

foreach (array_keys($rows[0]) as $col) {
    $displayCol = $col;
    $html .= "<th style='padding: 8px;'>" . htmlspecialchars($displayCol) . "</th>";
    if ($col === 'TD') {
        $html .= "<th style='padding: 8px;'>Total</th>";
    }
}
$html .= "</tr>";

// Data rows
foreach ($rows as $row) {
    $html .= "<tr>";
    $total = 0;
    foreach ($row as $key => $val) {
        $html .= "<td style='padding: 8px; text-align: center;'>" . htmlspecialchars($val) . "</td>";
        if (in_array($key, $productFields)) {
            $total += (float)$row[$key];
        }
        if ($key === 'TD') {
            $html .= "<td style='padding: 8px; text-align: center; background-color: #f9f9f9;'>" . intval($total) . "</td>";
        }
    }
    $html .= "</tr>";
}

$html .= "</table>";
echo $html;
?>