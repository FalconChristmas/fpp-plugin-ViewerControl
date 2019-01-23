#!/usr/bin/php
<?

if ($argc > 1)
{
	if ($argv[1] == "--list")
	{
		echo "nextplaylist\n";
		exit(0);
	}
	else if ($argv[1] == "--type" && $argv[2] == "nextplaylist")
	{
		echo "print the next playlist JSON here\n";
	}
}

?>
