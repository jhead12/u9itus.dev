web: PROCESS_ROLE=web ENABLE_REVERB=false ./wait-for-db.sh
scheduler: PROCESS_ROLE=scheduler ./wait-for-db.sh
queue: PROCESS_ROLE=queue ./wait-for-db.sh
reverb: PROCESS_ROLE=reverb REVERB_SERVER_PORT=6001 ./wait-for-db.sh
