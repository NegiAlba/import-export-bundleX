#!/bin/bash

c=1
for file in $1*
do
    if [[ -f $file ]]; then
        if [[ $file =~ ([0-9]{11}) ]]; then

            i=${BASH_REMATCH[1]}
            php bin/console galilee:import -t customer_price -v --from=$i --to=$i &

            if [ $(($c%5)) == 0 ]; then
                wait
            fi
            let c++
        fi
    fi
done