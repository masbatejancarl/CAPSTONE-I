The Dashboard currently ships with placeholder vector illustrations
(SVG) of a SonoBand smartwatch:

hero-sonoband.svg       - overview graphic (top of Dashboard)
feature-detection.svg   - "Real-Time Sound Detection" section
feature-alerts.svg      - "Instant Vibration & Visual Alerts" section
feature-lifestyle.svg   - "Built for Everyday Life" section

To swap in real product photos later, drop a JPG/PNG with the SAME
BASE NAME (e.g. hero-sonoband.jpg) and update the file extension in
user/dashboard.php ($features array + hero <img> src) to match.
Recommended aspect ratio: 4:3 (e.g. 1200x900px).

Contact Us section (Dashboard footer) expects developer photos:

dev-1.jpg  - "Developer One"
dev-2.jpg  - "Developer Two"
dev-3.jpg  - "Developer Three"

Recommended aspect ratio: 1:1 square (e.g. 400x400px), shown as a
circular avatar. Until a file is added, a person-icon placeholder is
shown instead of a broken image. Update names/roles/emails in the
$developers array at the top of user/dashboard.php.
