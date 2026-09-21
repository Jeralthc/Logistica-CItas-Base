Remove-Item -Recurse -Force deploy_v69 -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path deploy_v69\database\migrations -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v69\app\Http\Requests -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v69\app\Http\Controllers -Force | Out-Null

Copy-Item "database\migrations\2026_07_20_140000_drop_unique_email_from_users_table.php" -Destination "deploy_v69\database\migrations\" -Force
Copy-Item "app\Http\Requests\ProfileUpdateRequest.php" -Destination "deploy_v69\app\Http\Requests\" -Force
Copy-Item "app\Http\Controllers\CitaController.php" -Destination "deploy_v69\app\Http\Controllers\" -Force

Compress-Archive -Path "deploy_v69\*" -DestinationPath "V69_Correos_Compartidos_Proveedores.zip" -Force
Remove-Item -Recurse -Force deploy_v69
