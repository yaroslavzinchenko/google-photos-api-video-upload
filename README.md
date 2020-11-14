# google-photos-api-video-upload
Application that interacts with Google Photos Api, uploads all videos from certain directory and sorts them by albums according to the directory they are located in.

To make this application work, you need to put file named **client_secret.json** into the **public** folder.

**client_secret.json** can be downloaded from the Google Developers Console (you must create the identifier to download it).
For the reference check https://developers.google.com/photos/library/guides/overview

Also in the code define the constant **BASE_DIR**. This is the directory which you want to get scanned.

Example:
```
define('BASE_DIR', 'My/Full/Path');
```

The uploaded videos will be located in albums according to the directory they are located in.

For example, if the **BASE_URL** defined as **'C:\MyFolder'**, and there is a video that has a path **'C:\MyFolder/Subfolder1/Subfolder2/MyVideo'**,
the album name will be **'Subfolder1/Subfolder2'** and the video name will be **'MyVideo'**.
