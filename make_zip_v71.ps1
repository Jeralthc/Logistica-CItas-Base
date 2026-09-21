Remove-Item -Recurse -Force deploy_v71 -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path deploy_v71\public\build -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v71\resources\js\Pages -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v71\app\Http\Controllers -Force | Out-Null

Copy-Item "public\build\*" -Destination "deploy_v71\public\build\" -Recurse -Force
Copy-Item "resources\js\Pages\ReservarCita.vue" -Destination "deploy_v71\resources\js\Pages\" -Force
Copy-Item "app\Http\Controllers\LogisticaController.php" -Destination "deploy_v71\app\Http\Controllers\" -Force

Compress-Archive -Path "deploy_v71\*" -DestinationPath "V71_Multiples_Contactos_Mismo_RIF.zip" -Force
Remove-Item -Recurse -Force deploy_v71
