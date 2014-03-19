if exist "Final.flv" del "Final.flv"
for %%a in (*.beat) do php BeatConvert.php %%a
php FlvFixer.php --in Final.flv --out Final_fixed.flv --nometa
FFMpeg.exe -y -i Final_fixed.flv -c copy Final.mkv
