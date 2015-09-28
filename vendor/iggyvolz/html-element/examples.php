<?php
require "vendor/autoload.php";
/*
  htmlElement, a simple class to integrate PHP into HTML
  Copyright (C) 2014  iggyvolz

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License along
  with this program; if not, write to the Free Software Foundation, Inc.,
  51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
 
$html = new htmlElement('html'); // <html></html>
while($html->toggle()) // echoes <html> the first time and returns TRUE {thereby continuing the loop} and echoes </html> the second time and returns FALSE {thereby ending the loop}
{
	$head = new htmlElement('head');
	while($head->toggle())
	{
		$title = new htmlElement('title');
		while($title->toggle())
		{
			echo 'Here is the title!'; // This goes <title>Here is the title!</title>
		}
	}
	$body = new htmlElement('body');
	while($body->toggle())
	{
		$paragraph = new htmlElement('p',array('id'=>'myFirstParagraph','class'=>'thisIsAClass',style=>'text-align:center')); // This is how you define attributes
		while($paragraph->toggle())
		{
			echo 'I am in a paragraph!';
		}
		$linebreak = new htmlElement('br');
		$linebreak->turnon(); // You can even use turnon() and turnoff() outside of the class if you'd like.
	} // And everything closes itself!
}
