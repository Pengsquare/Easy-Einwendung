#!/bin/zsh

zip -r Easy-Einwendung-PlugIn-Latest.zip "Easy-Einwendung-PlugIn" -x "*.DS_Store" -x "__MACOSX/*" -x "*/._*" -x "._*"

mv -f Easy-Einwendung-PlugIn-Latest.zip ./Builds/