#!/bin/bash

cd "$(dirname "$0")"

npx @tailwindcss/cli -i ./input.css -o ./style.css --watch

