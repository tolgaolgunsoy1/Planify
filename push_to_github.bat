@echo off
title Push Planify to GitHub
echo ========================================================
echo Planify - Pushing to GitHub: tolgaolgunsoy1/Planify
echo ========================================================
set "PATH=C:\dev\flutter\bin\mingit\cmd;C:\Users\Administrator\bin;%PATH%"
git push -u origin main
echo.
echo Process complete.
pause
