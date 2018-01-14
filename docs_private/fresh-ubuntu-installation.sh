# fresh installation
apt-get update
apt-get upgrade

#### Things to install after ubuntu installation ####

echo "Downloading GetDeb and PlayDeb" && wget http://archive.getdeb.net/install_deb/getdeb-repository_0.1-1~getdeb1_all.deb http://archive.getdeb.net/install_deb/playdeb_0.3-1~getdeb1_all.deb &&
echo "Installing GetDeb" &&
dpkg -i getdeb-repository_0.1-1~getdeb1_all.deb &&
echo "Installing PlayDeb" &&
dpkg -i playdeb_0.3-1~getdeb1_all.deb &&
echo "Deleting Downloads" &&
rm -f getdeb-repository_0.1-1~getdeb1_all.deb &&
rm -f playdeb_0.3-1~getdeb1_all.deb

add-apt-repository -y ppa:webupd8team/y-ppa-manager
add-apt-repository -y ppa:otto-kesselgulasch/gimp
add-apt-repository -y ppa:gnome3-team/gnome3
add-apt-repository -y ppa:webupd8team/java
add-apt-repository -y ppa:webupd8team/y-ppa-manager
echo `deb http://download.videolan.org/pub/debian/stable/ /` | tee -a /etc/apt/sources.list.d/libdvdcss.list &&
echo `deb-src http://download.videolan.org/pub/debian/stable/ /` | sudo tee -a /etc/apt/sources.list.d/libdvdcs.list  && wget -O - http://download.videolan.org/pub/debian/videolan-apt.asc | sudo apt-key add -
apt-get update
apt-get upgrade
apt-get install ubuntu-restricted-extras
apt-get install dist-upgrade
apt-get install synaptic vlc gimp gimp-data gimp-plugin-registry gimp-data-extras y-ppa-manager bleachbit oracle-java8-installer flashplugin-installer unace unrar zip unzip p7zip-full p7zip-rar sharutils rar uudeview mpack arj cabextract file-roller mencoder flac faac faad sox ffmpeg2theora libmpeg2-4 uudeview mpeg3-utils mpegdemux mpeg2dec vorbis-tools id3v2 mpg312 mpg123 icedax lame libmad0 libjpeg-progs libdvdcss2 libdvdread4 libdvdnav4

if [[ $(getconf LONG_BIT) = "64" ]]
then
	echo "64bit detected" &&
	echo "installing google chrome" &&
	wget https://dl.google.com/linux/direct/google-chrome-stable_current_amd64.deb &&
	sudo dpkg -i google-chrome-stable_current_amd64.deb &&
	rm -f google-chrome-stable_current_amd64.deb
else
	echo "32 bit detected" &&
	echo "installing google chrome" &&
	wget https://dl.google.com/linux/direct/google-chrome-stable_current_i386.deb &&
	sudo dpkg -i google-chrome-stable_current_i386.deb &&
	rm -f google-chrome-stable_current_i386.deb
fi
echo "cleaning up"

apt-get -f install
apt-get autoremove
apt-get -y autoclean
apt-get -y clean

#### User Area ####

gsettings set org.compiz.unityshell:/org/compiz/profiles/unity/plugins/unitnyshell/ launcher-minimize-window true

#### Development PHP ####

add-apt-repository ppa:webupd8team/sublime-text-3
apt-get update

apt-get install vim git subversion mysql-server php apache2 phpmyadmin sublime-installer ack-grep cloc

cd ~
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '544e09ee996cdf60ece3804abc52599c22b1f40f4323403c44d44fdfdd586475ca9813a858088ffbc1f233e9b180f061') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --install-dir=/usr/bin
php -r "unlink('composer-setup.php');"
mv /usr/bin/composer.phar /usr/bin/composer
chmod +x /usr/bin/composer
mkdir dev_git && cd dev_git && git_clone https://github.com/RoverWire/virtualbox.git && cd virtualhost && chmod +x virtualhost.sh && sudo cp virtualhost.sh /usr/bin/virtualhost && chmod +x virtualhost-nginx.sh && sudo cp virtualhost-nginx.sh /usr/bin/virtualhost-nginx

#### JS extensions ####
sudo apt-get install npm

#### GITLAB GITHUB HOOKS ####
https://gist.github.com/insoul/2348958
https://github.com/phlegx/redmine_gitlab_hook
https://agencychief.com/blog/drupal-8-csv-migration

#### VM Drivers ####
sudo apt-get install exfat-fuse exfat-utils
sudo apt-get install -y nfs-server
sudo apt-get install vagrant virtualbox

## latest changes feed
#https://www.drupal.org/list-changes/drupal/published?keywords_description=&to_branch=&version=&created_op=%3E%3D&created%5Bvalue%5D=&created%5Bmin%5D=&created%5Bmax%5D=

## drupal virtual machine
#http://docs.drupalvm.com/en/latest/other/performance/

## load balancing with haproxy and nginx
#http://www.kalose.net/oss/drupal-8-load-balancing-haproxy/

mkdir -p /var/docker/elasticsearch/data_bin
docker run -d -p 9200:9200 -p 9300:9300 -v /var/docker/elasticsearch/data_bin:/usr/share/elasticsearch/data_bin elasticsearch
