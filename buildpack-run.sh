#!/bin/sh
pip install --upgrade pip
wget https://github.com/kalcaddle/KODExplorer/archive/master.zip
unzip master.zip 
mv KodExplorer-master files
chmod -Rf 777 ./*
