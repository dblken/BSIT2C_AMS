<?php
// Script to find files containing units references in SQL queries
$dir = __DIR__ . '/teacher';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

echo "Files potentially containing units references in SQL queries:\n";
foreach ($files as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        // Look for SQL SELECT statements that might reference units
        if (preg_match('/SELECT\s+(?:[^;]+?)units/i', $content)) {
            echo "- " . str_replace(__DIR__ . '/', '', $file->getPathname()) . "\n";
            
            // Extract and display the specific lines
            $lines = file($file->getPathname());
            foreach ($lines as $lineNum => $line) {
                if (stripos($line, 'units') !== false && (
                    stripos($line, 'SELECT') !== false || 
                    stripos($line, 'FROM') !== false ||
                    stripos($line, 'JOIN') !== false)) {
                    echo "  Line " . ($lineNum + 1) . ": " . trim($line) . "\n";
                }
            }
            echo "\n";
        }
    }
}
?> 