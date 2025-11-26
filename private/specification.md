# Overview
This is the specification for the wedding website of Jacob Stephens and Melissa Longua.

# URL of home page: https://wedding.stephens.page
The home page should be accessible at https://wedding.stephens.page.

# Conventions
Some assets have JM in the name. This indicates that the asset depicts Jacob and Melissa.

# Navigation
For desktop view, there should be links to different pages beneath the header of each page, as https://www.theknot.com/us/jack-dill-and-hannah-gifford-dec-2025/photos has. 

For mobile view, as this sample wedding site has, there should be a top right burger menu which can be clicked to open the menu.

# Pages

## Home
This page should have a background video or image behind the entire page with a dark overlay to make the text more ledgible, and a central header saying:

Jacob & Melissa
April 11, 2026 | Philadelphia
[Days] to go!

The [Days] should be the number of days from now until April 11, 2026.


## RSVP
There should be a page which lets visitors to the website RSVP. If a visitor RSVPs, an email notification of the reservation should be sent to melissa.longua@gmail.com.


## Story
This page should have several sections for the user to scroll down through, telling the story of Jacob and Melissa's relationship chronologically.

### Meeting
Jacob and Melissa met dancing in Philadelphia.

[2024-11-17_Rittenhop_Dip_Landscape.jpg]

### Proposal
Jacob proposed to Melissa in Banff National Park on a cliff of Mt. Jimmy Stepson, overlooking Peyto Lake, with guitarist Dave Hirschman playing 

[photos in wedding.stephens.page/private/photos/proposal]
[JM_Engagement-Blessing-mobile.mov]

### Blessing
The day after the proposal, Fr. Remi Morales of St. Agatha St. James church blessed Jacob and Melissa's engagement, surrounded my many friends and their parents. Afterwards, Jacob, Melissa, their parents, and Melissa's brother Matt went to dinner in South Philly at Scannichio's

[Photos in wedding.stephens.page/private/photos/blessing]
[wedding.stephens.page/private/videos/JM_Engagement-Blessing-mobile.mov]

### Wedding
The wedding will be on April 11, 2026 at St. Agatha St. James Parish in Philadelphia. The reception will be hosted at Bala Golf Club.

## Registry
There should be a page that displays Jacob and Melissa's registry. They have not created a registry yet, so this is coming soon.

## Contact
There should be a form that lets users send emails to Jacob and Melissa at melissa.longua@gmail.com. Mandrill SMTP can be used to send these emails, with credentials in the private .env file.

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