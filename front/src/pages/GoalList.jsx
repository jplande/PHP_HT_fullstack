import { useEffect, useState } from "react";
import { EditGoal, GoalTable } from "../components/organism";

const GoalList = () => {
    const [goals, setGoals] = useState([
        {
            id: "4",
            title: "Faire du sport",
            description: "Courir 3 fois par semaine",
            status: "En cours",
        },
        {
            id: "5",
            title: "Lire un livre",
            description: "Terminer un chapitre par jour",
            status: "Non commencé",
        },
        {
            id: "6",
            title: "Apprendre à tricoter",
            description: "Pratiquer 1H minutes par jour",
            status: "Terminé",
        },
    ]);

    const [editModalShow, setEditModalShow] = useState(false);
    const [goalToEdit, setGoalToEdit] = useState(null);

    // useEffect(() => {
    //     const fetchGoals = async () => {
    //         try {
    //             const response = await fetch("/api/v1/goals", {
    //                 method: "GET",
    //                 headers: {
    //                     "Content-Type": "application/json",
    //                 },
    //             });

    //             if (!response.ok) {
    //                 const dataError = await response.json();
    //                 console.log(dataError.error);
    //                 return;
    //             }
    //             const Data = await response.json();
    //             setGoals(Data);
    //             console.log("Goal fetched successfully:", response.status);
    //         } catch (error) {
    //             console.error("Error fetching goals:", error);
    //         }
    //     };

    //     fetchGoals();
    // }, []);

    const handleEdit = (goal) => {
        setGoalToEdit(goal);
        setEditModalShow(true);
        console.log("Editing goal:", goal);
    };
    const handleDelete = (id) => {
        console.log("Deleting goal with ID:", id);
    };

    return (
        <div className="container-fluid mt-4">
            <GoalTable
                goals={goals}
                onEdit={handleEdit}
                onDelete={handleDelete}
            />

            {editModalShow ? (
                <EditGoal
                    show={editModalShow}
                    onHide={() => setEditModalShow(false)}
                    goal={goalToEdit}
                    onSave={(updatedGoal) => {
                        setGoals((prevGoals) =>
                            prevGoals.map((g) =>
                                g.id === updatedGoal.id ? updatedGoal : g
                            )
                        );
                        setEditModalShow(false);
                    }}
                />
            ) : null}
        </div>
    );
};
export default GoalList;
