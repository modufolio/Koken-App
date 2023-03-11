# Koken

The purpose of this repo is to keep koken running on our machines/hosting providers.

Sadly, it appears that the Koken project has been abandoned by its owner.

Read more about it on: https://www.koken.me/ by the Co-Founder of Koken - Todd Dominey

Let me know if you encounter some bugs with installing or updating koken.

### Setup instructions
Created a new installer because it wasn't working in php8.0

1. download the zip with the tag 1.1.7 / unpack

2. Go to the url wwww.yourdomain.com/install.html

3. Fill the correct data to connect with the database (there are currently no system or database checks for this early version of the installer)

4. Follow the steps, enjoy.


 
### Update instructions

1. Remove all the files except the storage folder.

2. Download the lasted zip https://github.com/modufolio/Koken-App/tags

3. Unpack the zip

4. Copy all the folders / files except the storage folder.

5. Remove all the files in the storage/cache/api folder.

It should work now.
