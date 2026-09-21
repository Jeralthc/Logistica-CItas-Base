Remove-Item -Recurse -Force deploy_v72 -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path deploy_v72\database\migrations -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v72\app\Http\Requests\Auth -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v72\app\Http\Controllers -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v72\resources\js\Pages -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v72\public\build -Force | Out-Null

Copy-Item "database\migrations\2026_07_21_000000_add_rif_to_users_table.php" -Destination "deploy_v72\database\migrations\" -Force
Copy-Item "app\Http\Requests\Auth\LoginRequest.php" -Destination "deploy_v72\app\Http\Requests\Auth\" -Force
Copy-Item "app\Http\Controllers\CitaController.php" -Destination "deploy_v72\app\Http\Controllers\" -Force
Copy-Item "app\Http\Controllers\LogisticaController.php" -Destination "deploy_v72\app\Http\Controllers\" -Force
Copy-Item "resources\js\Pages\ReservarCita.vue" -Destination "deploy_v72\resources\js\Pages\" -Force
Copy-Item "public\build\*" -Destination "deploy_v72\public\build\" -Recurse -Force

Compress-Archive -Path "deploy_v72\*" -DestinationPath "V72_Cuentas_SubUsuarios_Independientes_RIF.zip" -Force
Remove-Item -Recurse -Force deploy_v72
