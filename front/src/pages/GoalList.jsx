import { useEffect, useState } from "react";
import { GoalTable } from "../components/organism";

const GoalList = () => {
    const [goals, setGoals] = useState([]);

    useEffect(() => {
        const fetchGoals = async () => {
            try {
                const response = await fetch("/api/v1/goals", {
                    method: "GET",
                    headers: {
                        "Content-Type": "application/json",
                    },
                });

                if (!response.ok) {
                    const dataError = await response.json();
                    console.log(dataError.error);
                    return;
                }
                const Data = await response.json();
                setGoals(Data);
                console.log("Goal fetched successfully:", response.status);
            } catch (error) {
                console.error("Error fetching goals:", error);
            }
        };

        fetchGoals();
    }, []);

    const handleEdit = (goal) => {
        /* ... */
    };
    const handleDelete = (id) => {
        /* ... */
    };

    return (
        <div className="container-fluid mt-4">
            <GoalTable
                goals={goals}
                onEdit={handleEdit}
                onDelete={handleDelete}
            />
        </div>
    );
};
export default GoalList;
