# Overview
This is the specification for the wedding website of Jacob Stephens and Melissa Longua.

# URL of home page: https://wedding.stephens.page
The home page should be accessible at https://wedding.stephens.page.

URLs should not end with .php suffixes, for example:

Wrong: https://wedding.stephens.page/registry.php
Correct: https://wedding.stephens.page/registry

# Conventions
Some assets have JM in the name. This indicates that the asset depicts Jacob and Melissa.

# Favicon
The favicon.ico should have a matcha colored background, be a circle, and have a white "JM" in the middle.

# Directory organization
There is a public folder, which files to be served publicly by HTTP should be in, and there is a private folder, which should not be accessible by HTTP.

# Navigation
For desktop view, there should be links to different pages beneath the header of each page, as https://www.theknot.com/us/jack-dill-and-hannah-gifford-dec-2025/photos has. 

For mobile view, as this sample wedding site has, there should be a top right burger menu which can be clicked to open the menu.

Clicking "Jacob & Melissa" in the menu should return the user to the home page.

# Pages

## Home
This page should have a background video or image behind the entire page with a dark overlay to make the text more ledgible, and a central header saying:

Jacob & Melissa
April 11, 2026 | Philadelphia
[Days] to go!

The [Days] should be the number of days from now until April 11, 2026.


## RSVP
There should be a page which lets visitors to the website RSVP. If a visitor RSVPs, an email notification of the reservation should be sent to melissa.longua@gmail.com.

The top of this page should note the locations of the wedding:

St. Agatha St. James Parish
3728 Chestnut St, Philadelphia, PA 19104

Bala Golf Club
2200 Belmont Ave, Philadelphia, PA 19131

### Storing RSVPs
In addition to emailing notice of RSVP's, RSVP's should be stored in a MySQL database.



## Story
This page should have several sections for the user to scroll down through, telling the story of Jacob and Melissa's relationship chronologically.

Visitors should be able to click the photos on this page to view them larger.

### Meeting
Jacob and Melissa met dancing in Philadelphia.

[2024-11-17_Rittenhop_Dip_Landscape.jpg]

### Proposal
Jacob proposed to Melissa in Banff National Park on a cliff of Mt. Jimmy Stepson, overlooking Peyto Lake, with guitarist Dave Hirschman playing 

[photos in wedding.stephens.page/private/photos/proposal]

This YouTube video of the proposal should be embedded in the proposal section of the story page: https://youtu.be/iEbqiWzH800

### Blessing
The day after the proposal, Fr. Remi Morales of St. Agatha St. James church blessed Jacob and Melissa's engagement, surrounded my many friends and their parents. Afterwards, Jacob, Melissa, their parents, and Melissa's brother Matt went to dinner in South Philly at Scannichio's

[Photos in wedding.stephens.page/private/photos/blessing]

This YouTube video of the blessing should be embedded in the blessing section of the story page: https://youtu.be/dko2cded45E

### Wedding
The wedding will be on April 11, 2026 at St. Agatha St. James Parish in Philadelphia. The reception will be hosted at Bala Golf Club.

The wedding section of the story page should include the photo(s) from the wedding.stephens.page/private/photos/reception folder, and the wedding.stephens.page/private/photos/wedding folder.

## Registry
There should be a page that displays Jacob and Melissa's registry. They have not created a registry yet, so this is coming soon.

## Contact
There should be a form that lets users send emails to Jacob and Melissa at melissa.longua@gmail.com. Mandrill SMTP can be used to send these emails, with credentials in the private .env file.

## Check RSVPs
There should be a password protected page accessible at https://wedding.stephens.page/check-rsvps, which is not listed in the site menus. The password is the RSVP_CHECK_PASSWORD value in the .env file. This page should show the user the RSVPs stored in the database.

There should be a link on the logged in and logged out view of this page which takes the user back to the main site.

## Blessing
Navigating to https://wedding.stephens.page/blessing should direct visitors to a page on the wedding website which has the content described in the blessing section of the story page.

The blessing page should not have a link to it in the navigation of the site, rather, it should only be acessible by direct URL access.


# Technology
This website has the following technologies available to it:
- Ubuntu 24.04.3
- Apache 2.4.58
- PHP 8.3.6
    - composer for package management
    - phpdotenv for environment variables
- MySQL 8.0.43 (credentials in .env file)
- JavaScript (Primarily run in Google Chrome)
- CSS
- HTML
- Playwright MCP Server for browsing
- MailChimp SMTP (Mandrill)
- certbot for SSL certificates

When updates are made to css and js files, references to them should be updated so as to bust clients' caches of these files.

# Style

## Colors
Our wedding colors are green and gold. Further, we like these naturally inspired colors for our published materials:
- Cloudy Sky Lavendar: #9597a3
- Thin Matcha: #7f8f65

## Elements
We like floral and leafy accents.

## Fonts
We like seriffed, formal, modern fonts - not too flowy, but more strong, such as:
- Cinzel Regular
- Beloved Script Regular

Text should be left justified, not fully justified, so as to prevent large rivers from text sections viewed on mobile.

# Footer
The footer should note that the website was created by Jacob Stephens, and possibly link to his portfolio page: https://stephens.page/

# Analytics by Google Tag
Here is the Google Tag for this site's Analytics:

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-DQN0TVHB1Z"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-DQN0TVHB1Z');
</script>