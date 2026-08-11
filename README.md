# Members
- Aaron Delgado

## Grader
- Host: 198.199.73.64
- Username: grader


Website: https://baddecisions.site/

GitHub Auto Deployment:
The website source material is now situated in a GitHub repository instead of how I was doing it before by editing the files directly in /var. Now I work from a folder called CSE135, which contains the .git repository and is connected to the GitHub repository. When I make changes, commit them, and push them to GitHub, a GitHub Actions workflow automatically runs. The workflow uses its own SSH private key to authenticate with the DigitalOcean server, and then uses rsync to deploy the contents of the repository to:

/var/www/baddecisions.site/public_html/

The deployment excludes Git metadata, GitHub workflow files, README.md, and hw1/report.html. The README.md is excluded so it is not publicly accessible through the website, while hw1/report.html is excluded because the GoAccess report is generated directly on the server and updates in real time. Excluding it also prevents rsync --delete from removing or overwriting the live report during future deployments.



Compression:
Before I enabled compression, Chrome DevTools showed that the HTML response size was around 0.9 KB and the load time was about 223 ms. After turning on gzip compression, DevTools showed Content-Encoding: gzip, and the response size went down to around 0.7 KB. After doing a hard refresh with Ctrl + Shift + R, the load time also went down to about 88 ms.

Server Header / ModSecurity:
I used the InMotion Hosting guide as a starting point to set up and configure ModSecurity for Apache. I installed libapache2-mod-security2 and enabled ModSecurity by copying over the recommended configuration and changing SecRuleEngine to On.

After that, I edited:

/etc/modsecurity/modsecurity.conf

and added:

SecServerSignature "CSE135 Server"

This changed the default Apache/Ubuntu server information that was being shown. After restarting Apache, I checked that the change worked by running:

curl -I https://baddecisions.site/

and it returned:

Server: CSE135 Server
