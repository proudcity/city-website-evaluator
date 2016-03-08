# city-website-evaluator
Searches a list of city / town names, tries to find website, compiles information about capabilities

### Sources

#### City / population data

http://www.census.gov/popest/data/cities/totals/2014/SUB-EST2014-3.html

#### Website information

http://www.statelocalgov.net/ + https://www.wikipedia.org/

In retrospect this would have probably been easier with just wikipedia, but the statelocal data set seemed very convenient initially

### Development Requirements

- PHP > 5.5
- Composer (https://getcomposer.org/)
- Ruby + Ruby Gems (https://rubygems.org/)
- Site inspector (https://github.com/benbalter/site-inspector)
- PhantomJS (http://phantomjs.org/download.html)
- Vagrant (https://www.vagrantup.com/downloads.html)

### Installing

```
git clone https://github.com/proudcity/city-website-evaluator
cd city-website-evaluator
composer install
```

Install Wappalyzer in project folder

```
cd city-website-evaluator
git clone https://github.com/AliasIO/Wappalyzer
cd Wappalyzer
vagrant up
```

### Running application

#### Get and concatenate city data

Find your data set from http://www.census.gov/popest/data/cities/totals/2014/SUB-EST2014-3.html, it is organized in state buckets, so its easiest to compile in a quick bash script to pull all of them.

#### Build the csv file for the simple city-list.csv from the data above

This file has the form:

```
City#State#Population#Website#"Max budget"#"Decision maker"#https#mobile#CMS#"Current provider"#ipv6#Payments#Alerts#"Homepage last updated"
```

#### Get a google developer project application set up

Go to https://console.developers.google.com/, set up a new application.

Make sure the project has access to the page-speed api: https://console.developers.google.com/apis/api/pagespeedonline/overview

This should be free (under the limits) unless you're running the script constantly.

Get your api key set up...
```
touch .env.json
```
Paste in your google applications key in the form
```
{
  "API_KEY":"YOUR GOOGLE CONSOLE PROJECT KEY",
}
```

#### Run your first pass for websites

With a full set of data loaded into city-list.csv, run the first script
```
php getWebsites.php
```
This will cycle through each entry line, and first try the http://www.statelocalgov.net/  entry, saving the result in a json file inside ```./state_website_pages/```.  Probably unneccessary, but good for honing your website data.

If there is no entry found there, wikipedia will be scraped and processed.

The results will be output in a new csv ```result-websites.csv``` you may need to tweak the script or your city data to get better coverage, but after we ended up with ~1/2 of cities with website entries.

#### Attempt to get stats about the websites available

With Wapplyzer running, and a fleshed out ```result-websites.csv```, run the stats script
```
php getStats.php
```
Each website in the file is run through
- site inspector for http / https, ipv6, preliminary framework information
- Wappalyzer for additional framwork / CMS information
- Google pagespeed for mobile readiness

The results from all of this is output to result-stats.csv.




