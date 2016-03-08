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
```

### Running application

#### Get and concatenate city data

Find your data set from http://www.census.gov/popest/data/cities/totals/2014/SUB-EST2014-3.html, it is organized in state buckets, so its easiest to compile in a quick bash script.

#### Build the csv file for the simple city-list.csv


#### Get a google 

```
touch .env.json
```
Paste in your google applications key in the form
```
{
  "API_KEY":"YOUR GOOGLE CONSOLE PROJECT KEY",
}
```
