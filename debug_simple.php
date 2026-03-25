<?php
passthru('php artisan tinker --execute="print_r(\App\Models\StoreIssueLine::where(\'store_issue_id\', 3)->join(\'store_issues\', \'store_issues.id\', \'=\', \'store_issue_lines.store_issue_id\')->select(\'store_issue_lines.id\', \'store_issues.issue_number\', \'store_issues.project_id\')->get()->toArray())"');
