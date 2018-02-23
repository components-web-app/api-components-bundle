#install cmake
yum install cmake

#install libtool
yum install libtool

#install a perl pkgconfig requirement
yum install perl-ExtUtils-PkgConfig.noarch

#get rabbitmq-c library

# Download the rabbitmq-c library @ version 0-9-1
git clone git://github.com/alanxz/rabbitmq-c.git /tmp/rabbitmq-c
cd /tmp/rabbitmq-c
# Enable and update the codegen git submodule
git submodule init
git submodule update
# Configure, compile and install
autoreconf -i && ./configure && make && sudo make install

#install the pecl extension
pecl install amqp
echo "extension=amqp.so" >> `php --ini | grep "Loaded Configuration" | sed -e "s|.*:\s*||"`
