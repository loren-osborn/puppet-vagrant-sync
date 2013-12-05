class vagrant_sync (
)
{
	package { 'rsync':
	  ensure => installed,
	}

	service { 'vagrant_sync':
	  enable    => true,
	  ensure    => running,
	  hasstatus => true,
	  require   => [
	  		Package['rsync'], 
	  		Exec['initial copy from shared to local'],
	  		File['/vagrant_local'],
	  		File['/usr/local/sbin/vagrant_sync'], 
	  		File['/etc/init.d/vagrant_sync'], 
	  		File['/etc/default/vagrant_sync'] ],
	  subscribe => File['/usr/local/sbin/vagrant_sync'], 
	}

	file {'/vagrant_local':
	  ensure => directory,
	  owner   => 'vagrant',
	  group   => 'vagrant',
	  mode    => '0644',
	}
	
	exec { 'initial copy from shared to local':
	  command => 'rsync --recursive --times --perms --links /vagrant/* /vagrant_local > /dev/null',
	  require   => [
	  		Package['rsync'], 
	  		File['/vagrant_local'] ]
	}

	file {'/usr/local/sbin/vagrant_sync':
	  ensure => present,
	  owner   => 'vagrant',
	  group   => 'vagrant',
	  mode    => '0755',
	  source  => '/vagrant/puppet/modules/vagrant_sync/files/vagrant_sync.sh',
	}

	file {'/etc/init.d/vagrant_sync':
	  ensure => present,
	  owner   => 'vagrant',
	  group   => 'vagrant',
	  mode    => '0755',
	  source  => '/vagrant/puppet/modules/vagrant_sync/files/vagrant_sync_init_d.sh',
	}

	file {'/etc/default/vagrant_sync':
	  ensure => present,
	  owner   => 'vagrant',
	  group   => 'vagrant',
	  mode    => '0644',
	  source  => '/vagrant/puppet/modules/vagrant_sync/files/vagrant_sync.defaults',
	}

	file {'/usr/local/bin/vagrant_force_resync':
	  ensure => present,
	  owner   => 'vagrant',
	  group   => 'vagrant',
	  mode    => '0755',
	  source  => '/vagrant/puppet/modules/vagrant_sync/files/vagrant_force_resync.sh',
	}


}
