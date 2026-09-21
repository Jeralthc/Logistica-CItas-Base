Remove-Item -Recurse -Force deploy_v70 -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Path deploy_v70\public\build -Force | Out-Null
New-Item -ItemType Directory -Path deploy_v70\resources\js\Pages -Force | Out-Null

Copy-Item "public\build\*" -Destination "deploy_v70\public\build\" -Recurse -Force
Copy-Item "resources\js\Pages\ReservarCita.vue" -Destination "deploy_v70\resources\js\Pages\" -Force

Compress-Archive -Path "deploy_v70\*" -DestinationPath "V70_Boton_Dashboard_Confirmacion_Cita.zip" -Force
Remove-Item -Recurse -Force deploy_v70
